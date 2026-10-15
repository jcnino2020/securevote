-- SecureVote demo election expansion: 28 additional elections + the 2 original elections = 30.
-- Run against an existing SecureVote database. No existing election, ballot, or vote is changed.
-- Dates are relative to the import time in Asia/Manila (+08:00). Re-running does not duplicate rows.
-- These are clearly labelled demo contests. No ballots or winners are fabricated.
SET time_zone = '+08:00';
START TRANSACTION;

-- Closed, open, and scheduled contests. Effective status also follows the date window in PHP.
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Library Advisory Board Election', 'Sample student election for library advisory board. Demo data for interface testing; not an official election.', NOW() - INTERVAL 80 DAY, NOW() - INTERVAL 75 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 80 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Library Advisory Board Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Campus Sustainability Council Election', 'Sample student election for campus sustainability council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 72 DAY, NOW() - INTERVAL 66 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 72 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Campus Sustainability Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Student Wellness Committee Election', 'Sample student election for student wellness committee. Demo data for interface testing; not an official election.', NOW() - INTERVAL 62 DAY, NOW() - INTERVAL 57 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 62 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Student Wellness Committee Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Arts and Culture Council Election', 'Sample student election for arts and culture council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 54 DAY, NOW() - INTERVAL 48 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 54 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Arts and Culture Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Athletics Student Board Election', 'Sample student election for athletics student board. Demo data for interface testing; not an official election.', NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 39 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 45 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Athletics Student Board Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Dormitory Community Council Election', 'Sample student election for dormitory community council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 37 DAY, NOW() - INTERVAL 30 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 37 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Dormitory Community Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: School of Business Council Election', 'Sample student election for school of business council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 22 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 28 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: School of Business Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Engineering Student Forum Election', 'Sample student election for engineering student forum. Demo data for interface testing; not an official election.', NOW() - INTERVAL 21 DAY, NOW() - INTERVAL 17 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 21 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Engineering Student Forum Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Health Sciences Council Election', 'Sample student election for health sciences council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 16 DAY, NOW() - INTERVAL 12 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 16 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Health Sciences Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Education Student Council Election', 'Sample student election for education student council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 11 DAY, NOW() - INTERVAL 8 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 11 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Education Student Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: First Year Student Assembly Election', 'Sample student election for first year student assembly. Demo data for interface testing; not an official election.', NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 5 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 7 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: First Year Student Assembly Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Graduate Student Council Election', 'Sample student election for graduate student council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 3 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 5 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Graduate Student Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Campus Food Advisory Board Election', 'Sample student election for campus food advisory board. Demo data for interface testing; not an official election.', NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 2 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 3 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Campus Food Advisory Board Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Community Outreach Council Election', 'Sample student election for community outreach council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY, 'closed', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 2 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Community Outreach Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Computer Studies Student Council Election', 'Sample student election for computer studies student council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 6 DAY, NOW() + INTERVAL 4 HOUR, 'open', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 6 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Computer Studies Student Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Student Media Board Election', 'Sample student election for student media board. Demo data for interface testing; not an official election.', NOW() - INTERVAL 5 DAY, NOW() + INTERVAL 2 DAY, 'open', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 5 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Student Media Board Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Academic Integrity Committee Election', 'Sample student election for academic integrity committee. Demo data for interface testing; not an official election.', NOW() - INTERVAL 4 DAY, NOW() + INTERVAL 3 DAY, 'open', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 4 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Academic Integrity Committee Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Campus Accessibility Council Election', 'Sample student election for campus accessibility council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 3 DAY, NOW() + INTERVAL 4 DAY, 'open', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 3 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Campus Accessibility Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Research and Innovation Council Election', 'Sample student election for research and innovation council. Demo data for interface testing; not an official election.', NOW() - INTERVAL 2 DAY, NOW() + INTERVAL 5 DAY, 'open', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 2 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Research and Innovation Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: International Student Forum Election', 'Sample student election for international student forum. Demo data for interface testing; not an official election.', NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 7 DAY, 'open', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 1 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: International Student Forum Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Student Activities Board Election', 'Sample student election for student activities board. Demo data for interface testing; not an official election.', NOW(), NOW() + INTERVAL 9 DAY, 'open', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Student Activities Board Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: University Events Committee Election', 'Sample student election for university events committee. Demo data for interface testing; not an official election.', NOW(), NOW() + INTERVAL 14 DAY, 'open', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: University Events Committee Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Environmental Action Council Election', 'Sample student election for environmental action council. Demo data for interface testing; not an official election.', NOW() + INTERVAL 1 DAY, NOW() + INTERVAL 6 DAY, 'scheduled', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() + INTERVAL 1 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Environmental Action Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Student Housing Board Election', 'Sample student election for student housing board. Demo data for interface testing; not an official election.', NOW() + INTERVAL 2 DAY, NOW() + INTERVAL 7 DAY, 'scheduled', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() + INTERVAL 2 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Student Housing Board Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Digital Learning Committee Election', 'Sample student election for digital learning committee. Demo data for interface testing; not an official election.', NOW() + INTERVAL 3 DAY, NOW() + INTERVAL 8 DAY, 'scheduled', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() + INTERVAL 3 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Digital Learning Committee Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Volunteer Leadership Council Election', 'Sample student election for volunteer leadership council. Demo data for interface testing; not an official election.', NOW() + INTERVAL 5 DAY, NOW() + INTERVAL 10 DAY, 'scheduled', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() + INTERVAL 5 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Volunteer Leadership Council Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Peer Mentoring Board Election', 'Sample student election for peer mentoring board. Demo data for interface testing; not an official election.', NOW() + INTERVAL 7 DAY, NOW() + INTERVAL 14 DAY, 'scheduled', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() + INTERVAL 7 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Peer Mentoring Board Election');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Demo: Campus Transportation Council Election', 'Sample student election for campus transportation council. Demo data for interface testing; not an official election.', NOW() + INTERVAL 10 DAY, NOW() + INTERVAL 17 DAY, 'scheduled', 'after_close',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       LEAST(NOW(), NOW() + INTERVAL 10 DAY - INTERVAL 2 DAY)
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Demo: Campus Transportation Council Election');

-- One contested position per demo election.
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Student Representative', 'Represents students in this council or committee.', 1, 1
FROM elections e
WHERE e.title IN (
    'Demo: Library Advisory Board Election',
    'Demo: Campus Sustainability Council Election',
    'Demo: Student Wellness Committee Election',
    'Demo: Arts and Culture Council Election',
    'Demo: Athletics Student Board Election',
    'Demo: Dormitory Community Council Election',
    'Demo: School of Business Council Election',
    'Demo: Engineering Student Forum Election',
    'Demo: Health Sciences Council Election',
    'Demo: Education Student Council Election',
    'Demo: First Year Student Assembly Election',
    'Demo: Graduate Student Council Election',
    'Demo: Campus Food Advisory Board Election',
    'Demo: Community Outreach Council Election',
    'Demo: Computer Studies Student Council Election',
    'Demo: Student Media Board Election',
    'Demo: Academic Integrity Committee Election',
    'Demo: Campus Accessibility Council Election',
    'Demo: Research and Innovation Council Election',
    'Demo: International Student Forum Election',
    'Demo: Student Activities Board Election',
    'Demo: University Events Committee Election',
    'Demo: Environmental Action Council Election',
    'Demo: Student Housing Board Election',
    'Demo: Digital Learning Committee Election',
    'Demo: Volunteer Leadership Council Election',
    'Demo: Peer Mentoring Board Election',
    'Demo: Campus Transportation Council Election'
)
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Student Representative');

-- Two candidates per position, with no synthetic votes.
INSERT INTO candidates (election_id, position_id, first_name, last_name, party, biography, platform, status)
SELECT e.id, p.id, seed.first_name, seed.last_name, seed.party, seed.biography, seed.platform, 'active'
FROM (
SELECT 'Demo: Library Advisory Board Election' AS title, 'Aira' AS first_name, 'Cruz' AS last_name, 'Students First' AS party, 'Student organizer focused on library advisory board.' AS biography, 'Improve representation and publish regular updates for library advisory board.' AS platform
UNION ALL
SELECT 'Demo: Library Advisory Board Election' AS title, 'Noel' AS first_name, 'Reyes' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on library advisory board.' AS biography, 'Improve representation and publish regular updates for library advisory board.' AS platform
UNION ALL
SELECT 'Demo: Campus Sustainability Council Election' AS title, 'Mika' AS first_name, 'Santos' AS last_name, 'Students First' AS party, 'Student organizer focused on campus sustainability council.' AS biography, 'Improve representation and publish regular updates for campus sustainability council.' AS platform
UNION ALL
SELECT 'Demo: Campus Sustainability Council Election' AS title, 'Jonah' AS first_name, 'Mendoza' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on campus sustainability council.' AS biography, 'Improve representation and publish regular updates for campus sustainability council.' AS platform
UNION ALL
SELECT 'Demo: Student Wellness Committee Election' AS title, 'Lea' AS first_name, 'Tan' AS last_name, 'Students First' AS party, 'Student organizer focused on student wellness committee.' AS biography, 'Improve representation and publish regular updates for student wellness committee.' AS platform
UNION ALL
SELECT 'Demo: Student Wellness Committee Election' AS title, 'Rico' AS first_name, 'Navarro' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on student wellness committee.' AS biography, 'Improve representation and publish regular updates for student wellness committee.' AS platform
UNION ALL
SELECT 'Demo: Arts and Culture Council Election' AS title, 'Tessa' AS first_name, 'Lim' AS last_name, 'Students First' AS party, 'Student organizer focused on arts and culture council.' AS biography, 'Improve representation and publish regular updates for arts and culture council.' AS platform
UNION ALL
SELECT 'Demo: Arts and Culture Council Election' AS title, 'Paolo' AS first_name, 'Bautista' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on arts and culture council.' AS biography, 'Improve representation and publish regular updates for arts and culture council.' AS platform
UNION ALL
SELECT 'Demo: Athletics Student Board Election' AS title, 'Nina' AS first_name, 'Torres' AS last_name, 'Students First' AS party, 'Student organizer focused on athletics student board.' AS biography, 'Improve representation and publish regular updates for athletics student board.' AS platform
UNION ALL
SELECT 'Demo: Athletics Student Board Election' AS title, 'Emil' AS first_name, 'Aquino' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on athletics student board.' AS biography, 'Improve representation and publish regular updates for athletics student board.' AS platform
UNION ALL
SELECT 'Demo: Dormitory Community Council Election' AS title, 'Cara' AS first_name, 'Castillo' AS last_name, 'Students First' AS party, 'Student organizer focused on dormitory community council.' AS biography, 'Improve representation and publish regular updates for dormitory community council.' AS platform
UNION ALL
SELECT 'Demo: Dormitory Community Council Election' AS title, 'Luis' AS first_name, 'Soriano' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on dormitory community council.' AS biography, 'Improve representation and publish regular updates for dormitory community council.' AS platform
UNION ALL
SELECT 'Demo: School of Business Council Election' AS title, 'Ivy' AS first_name, 'Morales' AS last_name, 'Students First' AS party, 'Student organizer focused on school of business council.' AS biography, 'Improve representation and publish regular updates for school of business council.' AS platform
UNION ALL
SELECT 'Demo: School of Business Council Election' AS title, 'Marco' AS first_name, 'Dizon' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on school of business council.' AS biography, 'Improve representation and publish regular updates for school of business council.' AS platform
UNION ALL
SELECT 'Demo: Engineering Student Forum Election' AS title, 'Elise' AS first_name, 'Pascual' AS last_name, 'Students First' AS party, 'Student organizer focused on engineering student forum.' AS biography, 'Improve representation and publish regular updates for engineering student forum.' AS platform
UNION ALL
SELECT 'Demo: Engineering Student Forum Election' AS title, 'Dante' AS first_name, 'Valdez' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on engineering student forum.' AS biography, 'Improve representation and publish regular updates for engineering student forum.' AS platform
UNION ALL
SELECT 'Demo: Health Sciences Council Election' AS title, 'Jessa' AS first_name, 'Garcia' AS last_name, 'Students First' AS party, 'Student organizer focused on health sciences council.' AS biography, 'Improve representation and publish regular updates for health sciences council.' AS platform
UNION ALL
SELECT 'Demo: Health Sciences Council Election' AS title, 'Renzo' AS first_name, 'Ramos' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on health sciences council.' AS biography, 'Improve representation and publish regular updates for health sciences council.' AS platform
UNION ALL
SELECT 'Demo: Education Student Council Election' AS title, 'Alma' AS first_name, 'Flores' AS last_name, 'Students First' AS party, 'Student organizer focused on education student council.' AS biography, 'Improve representation and publish regular updates for education student council.' AS platform
UNION ALL
SELECT 'Demo: Education Student Council Election' AS title, 'Theo' AS first_name, 'Villanueva' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on education student council.' AS biography, 'Improve representation and publish regular updates for education student council.' AS platform
UNION ALL
SELECT 'Demo: First Year Student Assembly Election' AS title, 'Lia' AS first_name, 'Rivera' AS last_name, 'Students First' AS party, 'Student organizer focused on first year student assembly.' AS biography, 'Improve representation and publish regular updates for first year student assembly.' AS platform
UNION ALL
SELECT 'Demo: First Year Student Assembly Election' AS title, 'Rafael' AS first_name, 'Gonzales' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on first year student assembly.' AS biography, 'Improve representation and publish regular updates for first year student assembly.' AS platform
UNION ALL
SELECT 'Demo: Graduate Student Council Election' AS title, 'Sofia' AS first_name, 'Del Rosario' AS last_name, 'Students First' AS party, 'Student organizer focused on graduate student council.' AS biography, 'Improve representation and publish regular updates for graduate student council.' AS platform
UNION ALL
SELECT 'Demo: Graduate Student Council Election' AS title, 'Caleb' AS first_name, 'Mercado' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on graduate student council.' AS biography, 'Improve representation and publish regular updates for graduate student council.' AS platform
UNION ALL
SELECT 'Demo: Campus Food Advisory Board Election' AS title, 'Bianca' AS first_name, 'Ocampo' AS last_name, 'Students First' AS party, 'Student organizer focused on campus food advisory board.' AS biography, 'Improve representation and publish regular updates for campus food advisory board.' AS platform
UNION ALL
SELECT 'Demo: Campus Food Advisory Board Election' AS title, 'Andrei' AS first_name, 'Salazar' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on campus food advisory board.' AS biography, 'Improve representation and publish regular updates for campus food advisory board.' AS platform
UNION ALL
SELECT 'Demo: Community Outreach Council Election' AS title, 'Mara' AS first_name, 'Luna' AS last_name, 'Students First' AS party, 'Student organizer focused on community outreach council.' AS biography, 'Improve representation and publish regular updates for community outreach council.' AS platform
UNION ALL
SELECT 'Demo: Community Outreach Council Election' AS title, 'Eli' AS first_name, 'Domingo' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on community outreach council.' AS biography, 'Improve representation and publish regular updates for community outreach council.' AS platform
UNION ALL
SELECT 'Demo: Computer Studies Student Council Election' AS title, 'Kara' AS first_name, 'Sison' AS last_name, 'Students First' AS party, 'Student organizer focused on computer studies student council.' AS biography, 'Improve representation and publish regular updates for computer studies student council.' AS platform
UNION ALL
SELECT 'Demo: Computer Studies Student Council Election' AS title, 'Andre' AS first_name, 'Aguilar' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on computer studies student council.' AS biography, 'Improve representation and publish regular updates for computer studies student council.' AS platform
UNION ALL
SELECT 'Demo: Student Media Board Election' AS title, 'Rina' AS first_name, 'Vega' AS last_name, 'Students First' AS party, 'Student organizer focused on student media board.' AS biography, 'Improve representation and publish regular updates for student media board.' AS platform
UNION ALL
SELECT 'Demo: Student Media Board Election' AS title, 'Nico' AS first_name, 'Chua' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on student media board.' AS biography, 'Improve representation and publish regular updates for student media board.' AS platform
UNION ALL
SELECT 'Demo: Academic Integrity Committee Election' AS title, 'Sam' AS first_name, 'Pineda' AS last_name, 'Students First' AS party, 'Student organizer focused on academic integrity committee.' AS biography, 'Improve representation and publish regular updates for academic integrity committee.' AS platform
UNION ALL
SELECT 'Demo: Academic Integrity Committee Election' AS title, 'Isabel' AS first_name, 'Abad' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on academic integrity committee.' AS biography, 'Improve representation and publish regular updates for academic integrity committee.' AS platform
UNION ALL
SELECT 'Demo: Campus Accessibility Council Election' AS title, 'Lara' AS first_name, 'Lopez' AS last_name, 'Students First' AS party, 'Student organizer focused on campus accessibility council.' AS biography, 'Improve representation and publish regular updates for campus accessibility council.' AS platform
UNION ALL
SELECT 'Demo: Campus Accessibility Council Election' AS title, 'Anton' AS first_name, 'Panganiban' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on campus accessibility council.' AS biography, 'Improve representation and publish regular updates for campus accessibility council.' AS platform
UNION ALL
SELECT 'Demo: Research and Innovation Council Election' AS title, 'Dana' AS first_name, 'Estrada' AS last_name, 'Students First' AS party, 'Student organizer focused on research and innovation council.' AS biography, 'Improve representation and publish regular updates for research and innovation council.' AS platform
UNION ALL
SELECT 'Demo: Research and Innovation Council Election' AS title, 'Miguel' AS first_name, 'Tiu' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on research and innovation council.' AS biography, 'Improve representation and publish regular updates for research and innovation council.' AS platform
UNION ALL
SELECT 'Demo: International Student Forum Election' AS title, 'Rhea' AS first_name, 'Guevara' AS last_name, 'Students First' AS party, 'Student organizer focused on international student forum.' AS biography, 'Improve representation and publish regular updates for international student forum.' AS platform
UNION ALL
SELECT 'Demo: International Student Forum Election' AS title, 'Ben' AS first_name, 'Alonzo' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on international student forum.' AS biography, 'Improve representation and publish regular updates for international student forum.' AS platform
UNION ALL
SELECT 'Demo: Student Activities Board Election' AS title, 'Ella' AS first_name, 'Marquez' AS last_name, 'Students First' AS party, 'Student organizer focused on student activities board.' AS biography, 'Improve representation and publish regular updates for student activities board.' AS platform
UNION ALL
SELECT 'Demo: Student Activities Board Election' AS title, 'Pio' AS first_name, 'Chan' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on student activities board.' AS biography, 'Improve representation and publish regular updates for student activities board.' AS platform
UNION ALL
SELECT 'Demo: University Events Committee Election' AS title, 'Mae' AS first_name, 'Yap' AS last_name, 'Students First' AS party, 'Student organizer focused on university events committee.' AS biography, 'Improve representation and publish regular updates for university events committee.' AS platform
UNION ALL
SELECT 'Demo: University Events Committee Election' AS title, 'Joel' AS first_name, 'Ferrer' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on university events committee.' AS biography, 'Improve representation and publish regular updates for university events committee.' AS platform
UNION ALL
SELECT 'Demo: Environmental Action Council Election' AS title, 'Aly' AS first_name, 'Francisco' AS last_name, 'Students First' AS party, 'Student organizer focused on environmental action council.' AS biography, 'Improve representation and publish regular updates for environmental action council.' AS platform
UNION ALL
SELECT 'Demo: Environmental Action Council Election' AS title, 'Sean' AS first_name, 'Sy' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on environmental action council.' AS biography, 'Improve representation and publish regular updates for environmental action council.' AS platform
UNION ALL
SELECT 'Demo: Student Housing Board Election' AS title, 'Celine' AS first_name, 'Manalo' AS last_name, 'Students First' AS party, 'Student organizer focused on student housing board.' AS biography, 'Improve representation and publish regular updates for student housing board.' AS platform
UNION ALL
SELECT 'Demo: Student Housing Board Election' AS title, 'Ian' AS first_name, 'Roxas' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on student housing board.' AS biography, 'Improve representation and publish regular updates for student housing board.' AS platform
UNION ALL
SELECT 'Demo: Digital Learning Committee Election' AS title, 'Jade' AS first_name, 'David' AS last_name, 'Students First' AS party, 'Student organizer focused on digital learning committee.' AS biography, 'Improve representation and publish regular updates for digital learning committee.' AS platform
UNION ALL
SELECT 'Demo: Digital Learning Committee Election' AS title, 'Lorenzo' AS first_name, 'Padilla' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on digital learning committee.' AS biography, 'Improve representation and publish regular updates for digital learning committee.' AS platform
UNION ALL
SELECT 'Demo: Volunteer Leadership Council Election' AS title, 'Riza' AS first_name, 'Uy' AS last_name, 'Students First' AS party, 'Student organizer focused on volunteer leadership council.' AS biography, 'Improve representation and publish regular updates for volunteer leadership council.' AS platform
UNION ALL
SELECT 'Demo: Volunteer Leadership Council Election' AS title, 'Enzo' AS first_name, 'Co' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on volunteer leadership council.' AS biography, 'Improve representation and publish regular updates for volunteer leadership council.' AS platform
UNION ALL
SELECT 'Demo: Peer Mentoring Board Election' AS title, 'Yna' AS first_name, 'Magtoto' AS last_name, 'Students First' AS party, 'Student organizer focused on peer mentoring board.' AS biography, 'Improve representation and publish regular updates for peer mentoring board.' AS platform
UNION ALL
SELECT 'Demo: Peer Mentoring Board Election' AS title, 'Owen' AS first_name, 'Lucero' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on peer mentoring board.' AS biography, 'Improve representation and publish regular updates for peer mentoring board.' AS platform
UNION ALL
SELECT 'Demo: Campus Transportation Council Election' AS title, 'Mina' AS first_name, 'Natividad' AS last_name, 'Students First' AS party, 'Student organizer focused on campus transportation council.' AS biography, 'Improve representation and publish regular updates for campus transportation council.' AS platform
UNION ALL
SELECT 'Demo: Campus Transportation Council Election' AS title, 'Javi' AS first_name, 'Aguirre' AS last_name, 'Campus Voice' AS party, 'Student organizer focused on campus transportation council.' AS biography, 'Improve representation and publish regular updates for campus transportation council.' AS platform
) seed
JOIN elections e ON e.title = seed.title
JOIN positions p ON p.election_id = e.id AND p.name = 'Student Representative'
WHERE NOT EXISTS (
  SELECT 1 FROM candidates c
  WHERE c.position_id = p.id AND c.first_name = seed.first_name AND c.last_name = seed.last_name
);

-- Register active voters for the demo contests, as the admin create flow does.
INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted)
SELECT e.id, u.id, 0
FROM elections e
JOIN users u ON u.role = 'voter' AND u.status = 'active'
WHERE e.title IN (
    'Demo: Library Advisory Board Election',
    'Demo: Campus Sustainability Council Election',
    'Demo: Student Wellness Committee Election',
    'Demo: Arts and Culture Council Election',
    'Demo: Athletics Student Board Election',
    'Demo: Dormitory Community Council Election',
    'Demo: School of Business Council Election',
    'Demo: Engineering Student Forum Election',
    'Demo: Health Sciences Council Election',
    'Demo: Education Student Council Election',
    'Demo: First Year Student Assembly Election',
    'Demo: Graduate Student Council Election',
    'Demo: Campus Food Advisory Board Election',
    'Demo: Community Outreach Council Election',
    'Demo: Computer Studies Student Council Election',
    'Demo: Student Media Board Election',
    'Demo: Academic Integrity Committee Election',
    'Demo: Campus Accessibility Council Election',
    'Demo: Research and Innovation Council Election',
    'Demo: International Student Forum Election',
    'Demo: Student Activities Board Election',
    'Demo: University Events Committee Election',
    'Demo: Environmental Action Council Election',
    'Demo: Student Housing Board Election',
    'Demo: Digital Learning Committee Election',
    'Demo: Volunteer Leadership Council Election',
    'Demo: Peer Mentoring Board Election',
    'Demo: Campus Transportation Council Election'
);
COMMIT;

-- Verify the import. Expect 28 demo elections and 30 total on the initial 2-election database.
SELECT COUNT(*) AS demo_elections,
       SUM(end_datetime <= NOW()) AS closed_by_deadline,
       SUM(start_datetime <= NOW() AND end_datetime > NOW()) AS open_now,
       SUM(start_datetime > NOW()) AS upcoming
FROM elections
WHERE title IN (
    'Demo: Library Advisory Board Election',
    'Demo: Campus Sustainability Council Election',
    'Demo: Student Wellness Committee Election',
    'Demo: Arts and Culture Council Election',
    'Demo: Athletics Student Board Election',
    'Demo: Dormitory Community Council Election',
    'Demo: School of Business Council Election',
    'Demo: Engineering Student Forum Election',
    'Demo: Health Sciences Council Election',
    'Demo: Education Student Council Election',
    'Demo: First Year Student Assembly Election',
    'Demo: Graduate Student Council Election',
    'Demo: Campus Food Advisory Board Election',
    'Demo: Community Outreach Council Election',
    'Demo: Computer Studies Student Council Election',
    'Demo: Student Media Board Election',
    'Demo: Academic Integrity Committee Election',
    'Demo: Campus Accessibility Council Election',
    'Demo: Research and Innovation Council Election',
    'Demo: International Student Forum Election',
    'Demo: Student Activities Board Election',
    'Demo: University Events Committee Election',
    'Demo: Environmental Action Council Election',
    'Demo: Student Housing Board Election',
    'Demo: Digital Learning Committee Election',
    'Demo: Volunteer Leadership Council Election',
    'Demo: Peer Mentoring Board Election',
    'Demo: Campus Transportation Council Election'
);
SELECT COUNT(*) AS total_elections FROM elections;
