Trainer Table (basic info of trainers):
CREATE TABLE trainer_profile (
  trainer_id INT PRIMARY KEY,
  specialization VARCHAR(100),
  experience_years INT,
  bio TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE
);


Patient Table (link trainers with patients):
CREATE TABLE patient_profile (
  patient_id INT PRIMARY KEY,
  condition_stage VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);


Trainer Activities Table (stores duties/tasks):
CREATE TABLE trainer_activity (
  activity_id INT PRIMARY KEY AUTO_INCREMENT,
  trainer_id INT NOT NULL,
  activity_type ENUM('Daily','Weekly','Occasional') NOT NULL,
  activity_description TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE
);

Trainer–Patient Mapping (Who is assigned to whom):
CREATE TABLE trainer_patient (
  tp_id INT PRIMARY KEY AUTO_INCREMENT,
  trainer_id INT NOT NULL,
  patient_id INT NOT NULL,
  assigned_date DATE,
  FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

Trainer Activity Log (Completion Tracking):
CREATE TABLE trainer_activity_log (
  log_id INT PRIMARY KEY AUTO_INCREMENT,
  activity_id INT NOT NULL,
  patient_id INT NOT NULL,
  log_date DATE NOT NULL,
  status ENUM('Pending','Completed') DEFAULT 'Pending',
  notes TEXT,
  FOREIGN KEY (activity_id) REFERENCES trainer_activity(activity_id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

🔹 Example Inserts (Adjusted DB)
Insert Trainer (in users)
INSERT INTO users (name, email, phone, dob, gender, address, role, username, password)
VALUES ('Dr. Rahman', 'rahman@example.com', '017xxxxxxxx', '1980-01-01', 'Male', 'Dhaka', 'trainer', 'rahmantrainer', 
'$2y$10$hQpOT7sQxHz5H7K8CwE1LeZLZpB9Onj.4Fg7pQFz4Q5j9yQmG0V4W'); 
-- password = trainer123
Insert Patient (in users)
INSERT INTO users (name, email, phone, dob, gender, address, role, username, password)
VALUES ('Rafiq Ahmed', 'rafiq@example.com', '018xxxxxxxx', '1995-06-10', 'Male', 'Chittagong', 'patient', 'rafiqpatient', 
'$2y$10$F5jz9HgqByQaP6sI9hP50eJw1nGhVtH7h6soQmt5dQ7PyqrxpXfSe');
-- password = patient123
Insert Trainer Activities
INSERT INTO trainer_activity (trainer_id, activity_type, activity_description) VALUES
(1, 'Daily', 'Check vitals & update patient dashboard'),
(1, 'Daily', 'Ensure medication reminders are followed'),
(1, 'Weekly', 'Review symptom trends with patient'),
(1, 'Weekly', 'Guide nutrition adjustments using Smart Nutrition Coach'),
(1, 'Occasional', 'Assist with uploading doctor notes'),
(1, 'Occasional', 'Conduct emergency drill for SOS feature');
Map Trainer → Patient
INSERT INTO trainer_patient (trainer_id, patient_id, assigned_date) VALUES
(1, 2, '2025-08-20'); -- trainer_id=1, patient_id=2
Insert Activity Log
INSERT INTO trainer_activity_log (activity_id, patient_id, log_date, status, notes) VALUES
(1, 2, '2025-08-22', 'Completed', 'Vitals checked, all normal');

