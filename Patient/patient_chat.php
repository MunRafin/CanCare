<?php
// AJAX Handler - Must be at the very top with no output before this
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    // Suppress all errors and warnings for clean JSON output
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Clean ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffer
    ob_start();
    
    try {
        // Set JSON header immediately
        header('Content-Type: application/json');
        
        // Check authentication
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
            throw new Exception('Authentication required');
        }
        
        $userMessage = trim($_POST['message'] ?? '');
        if (empty($userMessage)) {
            throw new Exception('Please enter a message');
        }
        
        // Try to get patient information and database context
        $patientName = 'there';
        $patientContext = '';
        try {
            // Suppress any database connection output
            ob_start();
            if (file_exists('../dbPC.php')) {
                require_once '../dbPC.php';
            }
            ob_end_clean();
            
            if (isset($conn) && $conn instanceof PDO) {
                // Get patient name and basic info
                $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && !empty($result['name'])) {
                    $patientName = $result['name'];
                    $patientContext = "Patient Name: " . $result['name'];
                    if (!empty($result['email'])) {
                        $patientContext .= "\nPatient Email: " . $result['email'];
                    }
                }
                
                // You can add more database queries here to get relevant patient data
                // For example: medical history, appointments, etc.
                // This is just a basic example - customize based on your database structure
                
                // Example: Get recent appointments (adjust table name and structure as needed)
                try {
                    $stmt = $conn->prepare("SELECT appointment_date, appointment_type, status FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC LIMIT 3");
                    $stmt->execute([$_SESSION['user_id']]);
                    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($appointments)) {
                        $patientContext .= "\n\nRecent Appointments:";
                        foreach ($appointments as $apt) {
                            $patientContext .= "\n- " . $apt['appointment_date'] . " (" . $apt['appointment_type'] . ") - " . $apt['status'];
                        }
                    }
                } catch (Exception $e) {
                    // Continue if appointments table doesn't exist or has different structure
                }
                
                // Example: Get patient medical conditions (adjust as needed)
                try {
                    $stmt = $conn->prepare("SELECT condition_name, diagnosis_date FROM medical_conditions WHERE user_id = ? ORDER BY diagnosis_date DESC LIMIT 5");
                    $stmt->execute([$_SESSION['user_id']]);
                    $conditions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($conditions)) {
                        $patientContext .= "\n\nMedical Conditions:";
                        foreach ($conditions as $condition) {
                            $patientContext .= "\n- " . $condition['condition_name'] . " (diagnosed: " . $condition['diagnosis_date'] . ")";
                        }
                    }
                } catch (Exception $e) {
                    // Continue if medical_conditions table doesn't exist
                }
            }
        } catch (Exception $dbError) {
            // Continue with basic response if database fails
            error_log("Database error in chat: " . $dbError->getMessage());
        }
        
        // Generate response using Gemini API
        $response = generateGeminiResponse($userMessage, $patientName, $patientContext);
        
        // Clean any remaining output and send JSON
        ob_clean();
        echo json_encode(['reply' => $response]);
        
    } catch (Exception $e) {
        // Clean output and send error
        ob_clean();
        http_response_code(500);
        echo json_encode(['reply' => 'I apologize, but I\'m having trouble processing your request right now. Please try again in a moment.']);
    }
    
    ob_end_flush();
    exit();
}

// Function to generate responses using Gemini API
function generateGeminiResponse($message, $patientName = 'there', $patientContext = '') {
    $apiKey = 'AIzaSyCwgkQ10ECAhiJDoCT8LYxiV_rPGOQ80Lw';
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;
    
    // Create the system prompt with patient context
    $systemPrompt = "You are a helpful AI health assistant for a medical platform called PersoCare. Your role is to provide general health information, wellness tips, and supportive guidance to patients.

IMPORTANT GUIDELINES:
- Always provide helpful, accurate general health information
- Be empathetic and supportive in your responses
- For specific medical concerns, always recommend consulting with healthcare providers
- Never provide specific medical diagnoses or treatment recommendations
- Keep responses concise but informative (2-3 paragraphs maximum)
- Use the patient's name when appropriate to personalize the response

PATIENT INFORMATION:
" . $patientContext . "

Patient's Question: " . $message;

    // Prepare the request data for Gemini API
    $requestData = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $systemPrompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 500,
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
        ]
    ];
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Handle cURL errors
    if ($error) {
        error_log("cURL Error: " . $error);
        return getFallbackResponse($message, $patientName);
    }
    
    // Handle HTTP errors
    if ($httpCode !== 200) {
        error_log("HTTP Error: " . $httpCode . " - " . $response);
        return getFallbackResponse($message, $patientName);
    }
    
    // Parse response
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Parse Error: " . json_last_error_msg());
        return getFallbackResponse($message, $patientName);
    }
    
    // Extract the generated text
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $generatedText = $responseData['candidates'][0]['content']['parts'][0]['text'];
        return trim($generatedText);
    } else {
        error_log("Unexpected API response structure: " . $response);
        return getFallbackResponse($message, $patientName);
    }
}

// Fallback response function for when Gemini API fails
function getFallbackResponse($message, $name = 'there') {
    $message = strtolower($message);
    
    // Health-related responses based on keywords
    $responses = [
        // Greeting responses
        'greeting' => [
            "Hello $name! I'm here to help with your health questions. What would you like to know?",
            "Hi $name! How can I assist you with your health and wellness today?",
            "Welcome $name! I'm ready to help answer your health-related questions."
        ],
        
        // Pain/symptom responses
        'pain' => [
            "I understand you're experiencing discomfort, $name. For persistent or severe pain, it's important to consult with your healthcare provider for proper evaluation and treatment.",
            "Pain can have many causes, $name. While I can provide general information, please consider speaking with your doctor if the pain continues or worsens.",
            "I'm sorry to hear about your pain, $name. For personalized advice and treatment, I recommend scheduling an appointment with your healthcare provider."
        ],
        
        // Medication responses
        'medication' => [
            "For questions about medications, dosages, or side effects, $name, please consult your doctor or pharmacist who can provide personalized guidance based on your medical history.",
            "Medication questions are best addressed by your healthcare provider or pharmacist, $name, as they can consider your specific situation and medical history.",
            "I recommend speaking with your doctor or pharmacist about medication concerns, $name, as they can provide the most accurate and safe guidance."
        ],
        
        // General health responses
        'general' => [
            "That's a great health question, $name! For personalized medical advice, I always recommend consulting with your healthcare provider who knows your medical history.",
            "I appreciate your interest in staying healthy, $name! Your doctor or healthcare team is the best source for advice specific to your situation.",
            "Health is very personal, $name, and what works for one person may not work for another. Your healthcare provider can give you the most appropriate guidance."
        ]
    ];
    
    // Determine response category based on message content
    if (preg_match('/\b(hello|hi|hey|good morning|good afternoon|good evening)\b/', $message)) {
        $category = 'greeting';
    } elseif (preg_match('/\b(pain|hurt|ache|sore|aching|painful)\b/', $message)) {
        $category = 'pain';
    } elseif (preg_match('/\b(medicine|medication|drug|pill|prescription|dose|dosage)\b/', $message)) {
        $category = 'medication';
    } else {
        $category = 'general';
    }
    
    // Return random response from category
    return $responses[$category][array_rand($responses[$category])];
}

// Regular page load starts here - only if not POST request
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not authenticated
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit;
}

// Get patient name safely
$patientName = 'Patient';
try {
    if (file_exists('../dbPC.php')) {
        require_once '../dbPC.php';
        if (isset($conn) && $conn instanceof PDO) {
            $stmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['name'])) {
                $patientName = htmlspecialchars($result['name']);
            }
        }
    }
} catch (Exception $e) {
    // Continue with default name
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PersoCare Chat - AI Health Assistant</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .chat-container {
            width: 100%;
            max-width: 900px;
            height: 700px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }
        
        .chat-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
            z-index: 1;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #1e40af 0%, #3730a3 100%);
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .chat-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .chat-subtitle {
            font-size: 16px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
        }
        
        .chat-messages {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
            z-index: 2;
        }
        
        .message {
            max-width: 80%;
            padding: 18px 24px;
            border-radius: 24px;
            line-height: 1.6;
            animation: messageSlide 0.4s ease-out;
            position: relative;
            word-wrap: break-word;
            font-size: 15px;
        }
        
        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .user-message {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 8px;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .ai-message {
            background: white;
            color: #1e293b;
            align-self: flex-start;
            border-bottom-left-radius: 8px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-left: 4px solid #3b82f6;
            position: relative;
        }
        
        .ai-message::before {
            content: '';
            position: absolute;
            left: -4px;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(to bottom, #3b82f6, #1e40af);
        }
        
        .loading-message {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            align-self: flex-start;
            border-bottom-left-radius: 8px;
            border-left: 4px solid #f59e0b;
            animation: loadingPulse 1.5s infinite;
        }
        
        @keyframes loadingPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.02); }
        }
        
        .error-message {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
            align-self: flex-start;
            border-bottom-left-radius: 8px;
            border-left: 4px solid #ef4444;
        }
        
        .message-icon {
            margin-right: 10px;
            font-size: 16px;
        }
        
        .chat-input-container {
            padding: 25px;
            background: white;
            border-top: 1px solid #e2e8f0;
            position: relative;
            z-index: 2;
        }
        
        .chat-input {
            display: flex;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            border-radius: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .chat-input:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .chat-input textarea {
            flex: 1;
            padding: 18px 24px;
            border: none;
            background: transparent;
            resize: none;
            outline: none;
            font-family: inherit;
            font-size: 16px;
            max-height: 120px;
            min-height: 56px;
            line-height: 1.5;
        }
        
        .chat-input textarea::placeholder {
            color: #9ca3af;
        }
        
        .send-button {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
            border: none;
            padding: 18px 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 120px;
            justify-content: center;
        }
        
        .send-button:hover:not(:disabled) {
            background: linear-gradient(135deg, #2563eb 0%, #1e3a8a 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }
        
        .send-button:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .send-button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            background: #92400e;
            border-radius: 50%;
            animation: typingAnimation 1.4s infinite ease-in-out;
        }
        
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes typingAnimation {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1.2); opacity: 1; }
        }
        
        /* Scrollbar styling */
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .chat-container {
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
            }
            
            .chat-messages {
                padding: 20px;
            }
            
            .message {
                max-width: 90%;
                padding: 15px 18px;
            }
            
            .chat-title {
                font-size: 24px;
            }
            
            .send-button {
                padding: 15px 20px;
                min-width: 100px;
            }
        }
        
        @media (max-width: 480px) {
            .chat-messages {
                padding: 15px;
                gap: 15px;
            }
            
            .chat-input-container {
                padding: 15px;
            }
            
            .message {
                padding: 12px 16px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <div class="chat-title">
                <i class="fas fa-robot"></i>
                PersoCare AI
            </div>
            <div class="chat-subtitle">
                <span class="status-indicator"></span>
                Powered by Google Gemini
            </div>
        </div>
        
        <div id="chatMessages" class="chat-messages">
            <div class="ai-message message">
                <i class="fas fa-hand-wave message-icon" style="color: #f59e0b;"></i>
                Hello <strong><?= $patientName ?></strong>! I'm your PersoCare AI assistant, powered by Google Gemini. I'm here to provide personalized health information and guidance based on your profile and medical history. 
                <br><br>
                <strong>Please note:</strong> I can offer general health information and insights about your care, but for specific medical concerns, always consult with your healthcare provider. What can I help you with today?
            </div>
        </div>
        
        <div class="chat-input-container">
            <div class="chat-input">
                <textarea 
                    id="userMessage" 
                    placeholder="Ask me about your health, medications, appointments, or general wellness topics..." 
                    rows="1"
                ></textarea>
                <button id="sendBtn" class="send-button" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                    <span>Send</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        let isProcessing = false;
        let messageCounter = 0;

        function sendMessage() {
            if (isProcessing) return;
            
            const textarea = document.getElementById("userMessage");
            const sendBtn = document.getElementById("sendBtn");
            const msg = textarea.value.trim();
            
            if (!msg) {
                textarea.focus();
                return;
            }

            // Set processing state
            isProcessing = true;
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Sending</span>';

            const chat = document.getElementById("chatMessages");
            
            // Add user message
            const userMsgDiv = document.createElement("div");
            userMsgDiv.className = "user-message message";
            userMsgDiv.textContent = msg;
            chat.appendChild(userMsgDiv);
            
            // Clear and resize textarea
            textarea.value = "";
            textarea.style.height = "auto";
            chat.scrollTop = chat.scrollHeight;

            // Add loading message with typing indicator
            const loadingId = "load-" + (++messageCounter);
            const loadingDiv = document.createElement("div");
            loadingDiv.id = loadingId;
            loadingDiv.className = "loading-message message";
            loadingDiv.innerHTML = `
                <i class="fas fa-brain message-icon"></i>
                Analyzing your question with Gemini AI
                <span class="typing-indicator">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </span>
            `;
            chat.appendChild(loadingDiv);
            chat.scrollTop = chat.scrollHeight;

            // Create form data
            const formData = new FormData();
            formData.append('message', msg);

            // Send request with timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout for AI processing

            fetch(window.location.pathname, {
                method: "POST",
                body: formData,
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`Server responded with status: ${response.status}`);
                }
                
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error("Invalid response format received");
                }
                
                return response.json();
            })
            .then(data => {
                // Remove loading message
                const loadingElement = document.getElementById(loadingId);
                if (loadingElement) {
                    loadingElement.remove();
                }
                
                // Add AI response
                const aiMsgDiv = document.createElement("div");
                aiMsgDiv.className = "ai-message message";
                
                // Format the response text to preserve line breaks
                let responseText = data.reply || "I apologize, but I didn't receive a proper response. Please try again.";
                responseText = responseText.replace(/\n/g, '<br>');
                
                aiMsgDiv.innerHTML = `<i class="fas fa-robot message-icon" style="color: #3b82f6;"></i>${responseText}`;
                chat.appendChild(aiMsgDiv);
                chat.scrollTop = chat.scrollHeight;
            })
            .catch(err => {
                clearTimeout(timeoutId);
                console.error("Chat error:", err);
                
                // Remove loading message
                const loadingElement = document.getElementById(loadingId);
                if (loadingElement) {
                    loadingElement.remove();
                }
                
                // Determine error message
                let errorMessage = "I apologize, but I'm having trouble right now. Please try again in a moment.";
                if (err.name === 'AbortError') {
                    errorMessage = "The request timed out. Please try again with a shorter message.";
                } else if (err.message.includes('Invalid response format')) {
                    errorMessage = "I received an unexpected response. Please try again.";
                }
                
                // Show error message
                const errorDiv = document.createElement("div");
                errorDiv.className = "error-message message";
                errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle message-icon"></i>${errorMessage}`;
                chat.appendChild(errorDiv);
                chat.scrollTop = chat.scrollHeight;
            })
            .finally(() => {
                // Reset UI state
                isProcessing = false;
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i><span>Send</span>';
                textarea.focus();
            });
        }

        // Enhanced keyboard handling
        document.getElementById("userMessage").addEventListener("keydown", function(e) {
            if (e.key === "Enter") {
                if (e.shiftKey) {
                    // Allow Shift+Enter for new line
                    return;
                } else {
                    // Send message on Enter
                    e.preventDefault();
                    sendMessage();
                }
            }
        });

        // Auto-resize textarea with smooth animation
        document.getElementById("userMessage").addEventListener("input", function() {
            this.style.height = "auto";
            const newHeight = Math.min(this.scrollHeight, 120);
            this.style.height = newHeight + "px";
        });

        // Focus on textarea when page loads
        window.addEventListener("load", function() {
            document.getElementById("userMessage").focus();
        });

        // Add some example prompts for better UX
        const examplePrompts = [
            "Tell me about my recent appointments",
            "What are healthy eating habits for my condition?",
            "How can I manage my medications better?",
            "What exercise is safe for me?",
            "Explain my medical history",
            "What should I discuss with my doctor next visit?"
        ];

        // Optional: Add click-to-send functionality for example prompts
        function addExamplePrompt(prompt) {
            document.getElementById("userMessage").value = prompt;
            document.getElementById("userMessage").focus();
        }
    </script>
</body>
</html>