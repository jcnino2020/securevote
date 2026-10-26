-- SecureVote: 50 additional student elections, October 15 through December 31, 2026 (Asia/Manila).
-- Five positions and three fictional candidates per position; no ballots or votes are fabricated.
-- Re-running adds only missing elections, positions, candidates, and voter eligibility.
-- Execute as a transaction; the accompanying deployment runner wraps these statements.
SET time_zone = '+08:00';
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Campus Safety Council Election 2026', 'Student election for the campus safety council. Candidates shown are fictional sample entries.', '2026-10-15 08:00:00', '2026-10-18 23:59:00', 'open', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Campus Safety Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Technology Council Election 2026', 'Student election for the student technology council. Candidates shown are fictional sample entries.', '2026-10-17 08:00:00', '2026-10-20 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Technology Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Library Services Committee Election 2026', 'Student election for the library services committee. Candidates shown are fictional sample entries.', '2026-10-18 08:00:00', '2026-10-21 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Library Services Committee Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Residence Hall Advisory Council Election 2026', 'Student election for the residence hall advisory council. Candidates shown are fictional sample entries.', '2026-10-20 08:00:00', '2026-10-23 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Residence Hall Advisory Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Wellness Ambassadors Board Election 2026', 'Student election for the wellness ambassadors board. Candidates shown are fictional sample entries.', '2026-10-21 08:00:00', '2026-10-24 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Wellness Ambassadors Board Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Research Council Election 2026', 'Student election for the student research council. Candidates shown are fictional sample entries.', '2026-10-23 08:00:00', '2026-10-26 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Research Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Community Service Council Election 2026', 'Student election for the community service council. Candidates shown are fictional sample entries.', '2026-10-24 08:00:00', '2026-10-27 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Community Service Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Arts Program Council Election 2026', 'Student election for the arts program council. Candidates shown are fictional sample entries.', '2026-10-26 08:00:00', '2026-10-29 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Arts Program Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Sports Facilities Committee Election 2026', 'Student election for the sports facilities committee. Candidates shown are fictional sample entries.', '2026-10-27 08:00:00', '2026-10-30 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Sports Facilities Committee Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Sustainability Projects Board Election 2026', 'Student election for the sustainability projects board. Candidates shown are fictional sample entries.', '2026-10-29 08:00:00', '2026-11-01 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Sustainability Projects Board Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Dining Council Election 2026', 'Student election for the student dining council. Candidates shown are fictional sample entries.', '2026-10-30 08:00:00', '2026-11-02 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Dining Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Accessibility Advisory Board Election 2026', 'Student election for the accessibility advisory board. Candidates shown are fictional sample entries.', '2026-11-01 08:00:00', '2026-11-04 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Accessibility Advisory Board Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'First Year Mentorship Council Election 2026', 'Student election for the first year mentorship council. Candidates shown are fictional sample entries.', '2026-11-02 08:00:00', '2026-11-05 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'First Year Mentorship Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Graduate Engagement Committee Election 2026', 'Student election for the graduate engagement committee. Candidates shown are fictional sample entries.', '2026-11-04 08:00:00', '2026-11-07 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Graduate Engagement Committee Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'International Programs Council Election 2026', 'Student election for the international programs council. Candidates shown are fictional sample entries.', '2026-11-05 08:00:00', '2026-11-08 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'International Programs Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Media Council Election 2026', 'Student election for the student media council. Candidates shown are fictional sample entries.', '2026-11-07 08:00:00', '2026-11-10 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Media Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Learning Resources Committee Election 2026', 'Student election for the learning resources committee. Candidates shown are fictional sample entries.', '2026-11-08 08:00:00', '2026-11-11 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Learning Resources Committee Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Events Council Election 2026', 'Student election for the student events council. Candidates shown are fictional sample entries.', '2026-11-10 08:00:00', '2026-11-13 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Events Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Career Development Council Election 2026', 'Student election for the career development council. Candidates shown are fictional sample entries.', '2026-11-12 08:00:00', '2026-11-15 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Career Development Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Campus Mobility Council Election 2026', 'Student election for the campus mobility council. Candidates shown are fictional sample entries.', '2026-11-13 08:00:00', '2026-11-16 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Campus Mobility Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Volunteer Programs Board Election 2026', 'Student election for the volunteer programs board. Candidates shown are fictional sample entries.', '2026-11-15 08:00:00', '2026-11-18 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Volunteer Programs Board Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Finance Advisory Council Election 2026', 'Student election for the student finance advisory council. Candidates shown are fictional sample entries.', '2026-11-16 08:00:00', '2026-11-19 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Finance Advisory Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Cultural Exchange Council Election 2026', 'Student election for the cultural exchange council. Candidates shown are fictional sample entries.', '2026-11-18 08:00:00', '2026-11-21 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Cultural Exchange Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Academic Support Council Election 2026', 'Student election for the academic support council. Candidates shown are fictional sample entries.', '2026-11-19 08:00:00', '2026-11-22 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Academic Support Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Leadership Council Election 2026', 'Student election for the student leadership council. Candidates shown are fictional sample entries.', '2026-11-21 08:00:00', '2026-11-24 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Leadership Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Design and Innovation Council Election 2026', 'Student election for the design and innovation council. Candidates shown are fictional sample entries.', '2026-11-22 08:00:00', '2026-11-25 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Design and Innovation Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Recreation Programs Board Election 2026', 'Student election for the recreation programs board. Candidates shown are fictional sample entries.', '2026-11-24 08:00:00', '2026-11-27 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Recreation Programs Board Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Housing Advisory Council Election 2026', 'Student election for the student housing advisory council. Candidates shown are fictional sample entries.', '2026-11-25 08:00:00', '2026-11-28 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Housing Advisory Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Campus Green Spaces Council Election 2026', 'Student election for the campus green spaces council. Candidates shown are fictional sample entries.', '2026-11-27 08:00:00', '2026-11-30 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Campus Green Spaces Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Peer Support Council Election 2026', 'Student election for the peer support council. Candidates shown are fictional sample entries.', '2026-11-28 08:00:00', '2026-12-01 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Peer Support Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Health Advocacy Council Election 2026', 'Student election for the student health advocacy council. Candidates shown are fictional sample entries.', '2026-11-30 08:00:00', '2026-12-03 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Health Advocacy Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Digital Campus Committee Election 2026', 'Student election for the digital campus committee. Candidates shown are fictional sample entries.', '2026-12-01 08:00:00', '2026-12-04 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Digital Campus Committee Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'University Traditions Council Election 2026', 'Student election for the university traditions council. Candidates shown are fictional sample entries.', '2026-12-03 08:00:00', '2026-12-06 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'University Traditions Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Entrepreneurship Student Council Election 2026', 'Student election for the entrepreneurship student council. Candidates shown are fictional sample entries.', '2026-12-05 08:00:00', '2026-12-08 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Entrepreneurship Student Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Creative Arts Assembly Election 2026', 'Student election for the creative arts assembly. Candidates shown are fictional sample entries.', '2026-12-06 08:00:00', '2026-12-09 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Creative Arts Assembly Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Rights Council Election 2026', 'Student election for the student rights council. Candidates shown are fictional sample entries.', '2026-12-08 08:00:00', '2026-12-11 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Rights Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Campus Communications Board Election 2026', 'Student election for the campus communications board. Candidates shown are fictional sample entries.', '2026-12-09 08:00:00', '2026-12-12 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Campus Communications Board Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Public Service Student Council Election 2026', 'Student election for the public service student council. Candidates shown are fictional sample entries.', '2026-12-11 08:00:00', '2026-12-14 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Public Service Student Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Science Student Society Election 2026', 'Student election for the science student society. Candidates shown are fictional sample entries.', '2026-12-12 08:00:00', '2026-12-15 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Science Student Society Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Humanities Student Society Election 2026', 'Student election for the humanities student society. Candidates shown are fictional sample entries.', '2026-12-14 08:00:00', '2026-12-17 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Humanities Student Society Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Business Student Society Election 2026', 'Student election for the business student society. Candidates shown are fictional sample entries.', '2026-12-15 08:00:00', '2026-12-18 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Business Student Society Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Engineering Student Society Election 2026', 'Student election for the engineering student society. Candidates shown are fictional sample entries.', '2026-12-17 08:00:00', '2026-12-20 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Engineering Student Society Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Education Student Society Election 2026', 'Student election for the education student society. Candidates shown are fictional sample entries.', '2026-12-18 08:00:00', '2026-12-21 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Education Student Society Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Health Professions Student Society Election 2026', 'Student election for the health professions student society. Candidates shown are fictional sample entries.', '2026-12-20 08:00:00', '2026-12-23 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Health Professions Student Society Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Computing Student Society Election 2026', 'Student election for the computing student society. Candidates shown are fictional sample entries.', '2026-12-21 08:00:00', '2026-12-24 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Computing Student Society Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Music and Performance Council Election 2026', 'Student election for the music and performance council. Candidates shown are fictional sample entries.', '2026-12-23 08:00:00', '2026-12-26 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Music and Performance Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Library Outreach Council Election 2026', 'Student election for the library outreach council. Candidates shown are fictional sample entries.', '2026-12-24 08:00:00', '2026-12-27 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Library Outreach Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Local Partnerships Council Election 2026', 'Student election for the local partnerships council. Candidates shown are fictional sample entries.', '2026-12-26 08:00:00', '2026-12-29 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Local Partnerships Council Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Student Wellbeing Assembly Election 2026', 'Student election for the student wellbeing assembly. Candidates shown are fictional sample entries.', '2026-12-27 08:00:00', '2026-12-30 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Student Wellbeing Assembly Election 2026');
INSERT INTO elections (title, description, start_datetime, end_datetime, status, results_visibility, created_by, created_at)
SELECT 'Year End Student Forum Election 2026', 'Student election for the year end student forum. Candidates shown are fictional sample entries.', '2026-12-29 08:00:00', '2026-12-31 23:59:00', 'scheduled', 'after_vote',
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1), NOW()
WHERE NOT EXISTS (SELECT 1 FROM elections WHERE title = 'Year End Student Forum Election 2026');
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Chairperson', 'Leads meetings and represents the student group.', 1, 1
FROM elections e
WHERE e.title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
)
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Chairperson');
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Vice Chairperson', 'Supports the chairperson and student projects.', 1, 2
FROM elections e
WHERE e.title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
)
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Vice Chairperson');
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Secretary', 'Maintains records and shares meeting updates.', 1, 3
FROM elections e
WHERE e.title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
)
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Secretary');
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Treasurer', 'Oversees budgets and reports on student funds.', 1, 4
FROM elections e
WHERE e.title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
)
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Treasurer');
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Student Representative', 'Brings student concerns and ideas to the group.', 1, 5
FROM elections e
WHERE e.title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
)
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Student Representative');
INSERT INTO candidates (election_id, position_id, first_name, last_name, party, biography, platform, status)
SELECT e.id, p.id, ELT(1 + MOD(p.id * 3 + 1 * 11, 30), 'Aira', 'Noel', 'Mika', 'Jonah', 'Lea', 'Rafael', 'Sofia', 'Nico', 'Isabel', 'Marco', 'Trisha', 'Paolo', 'Mara', 'Kenji', 'Dana', 'Luis', 'Aya', 'Miguel', 'Tala', 'Enzo', 'Rina', 'Carlo', 'Bea', 'Andre', 'Lia', 'Jared', 'Nina', 'Sam', 'Celine', 'Diego'), ELT(1 + MOD(e.id * 7 + p.id * 3 + 1 * 13, 30), 'Cruz', 'Reyes', 'Santos', 'Mendoza', 'Tan', 'Garcia', 'Lim', 'Chua', 'Ramos', 'Dela Cruz', 'Navarro', 'Bautista', 'Flores', 'Villanueva', 'Castillo', 'Aquino', 'Pascual', 'Torres', 'Mercado', 'Rivera', 'De Leon', 'Sy', 'Gonzales', 'Salazar', 'Ocampo', 'Valdez', 'Diaz', 'Lopez', 'Soriano', 'Manalo'), 'Students First',
       CONCAT('Student candidate for ', p.name, ' in ', e.title, '.'),
       CONCAT('Represent students and publish updates for ', e.title, '.'), 'active'
FROM positions p JOIN elections e ON e.id = p.election_id
WHERE e.title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
)
  AND (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id AND c.status = 'active') < 1;
INSERT INTO candidates (election_id, position_id, first_name, last_name, party, biography, platform, status)
SELECT e.id, p.id, ELT(1 + MOD(p.id * 3 + 2 * 11, 30), 'Aira', 'Noel', 'Mika', 'Jonah', 'Lea', 'Rafael', 'Sofia', 'Nico', 'Isabel', 'Marco', 'Trisha', 'Paolo', 'Mara', 'Kenji', 'Dana', 'Luis', 'Aya', 'Miguel', 'Tala', 'Enzo', 'Rina', 'Carlo', 'Bea', 'Andre', 'Lia', 'Jared', 'Nina', 'Sam', 'Celine', 'Diego'), ELT(1 + MOD(e.id * 7 + p.id * 3 + 2 * 13, 30), 'Cruz', 'Reyes', 'Santos', 'Mendoza', 'Tan', 'Garcia', 'Lim', 'Chua', 'Ramos', 'Dela Cruz', 'Navarro', 'Bautista', 'Flores', 'Villanueva', 'Castillo', 'Aquino', 'Pascual', 'Torres', 'Mercado', 'Rivera', 'De Leon', 'Sy', 'Gonzales', 'Salazar', 'Ocampo', 'Valdez', 'Diaz', 'Lopez', 'Soriano', 'Manalo'), 'Campus Voice',
       CONCAT('Student candidate for ', p.name, ' in ', e.title, '.'),
       CONCAT('Represent students and publish updates for ', e.title, '.'), 'active'
FROM positions p JOIN elections e ON e.id = p.election_id
WHERE e.title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
)
  AND (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id AND c.status = 'active') < 2;
INSERT INTO candidates (election_id, position_id, first_name, last_name, party, biography, platform, status)
SELECT e.id, p.id, ELT(1 + MOD(p.id * 3 + 3 * 11, 30), 'Aira', 'Noel', 'Mika', 'Jonah', 'Lea', 'Rafael', 'Sofia', 'Nico', 'Isabel', 'Marco', 'Trisha', 'Paolo', 'Mara', 'Kenji', 'Dana', 'Luis', 'Aya', 'Miguel', 'Tala', 'Enzo', 'Rina', 'Carlo', 'Bea', 'Andre', 'Lia', 'Jared', 'Nina', 'Sam', 'Celine', 'Diego'), ELT(1 + MOD(e.id * 7 + p.id * 3 + 3 * 13, 30), 'Cruz', 'Reyes', 'Santos', 'Mendoza', 'Tan', 'Garcia', 'Lim', 'Chua', 'Ramos', 'Dela Cruz', 'Navarro', 'Bautista', 'Flores', 'Villanueva', 'Castillo', 'Aquino', 'Pascual', 'Torres', 'Mercado', 'Rivera', 'De Leon', 'Sy', 'Gonzales', 'Salazar', 'Ocampo', 'Valdez', 'Diaz', 'Lopez', 'Soriano', 'Manalo'), 'Independent',
       CONCAT('Student candidate for ', p.name, ' in ', e.title, '.'),
       CONCAT('Represent students and publish updates for ', e.title, '.'), 'active'
FROM positions p JOIN elections e ON e.id = p.election_id
WHERE e.title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
)
  AND (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id AND c.status = 'active') < 3;
INSERT IGNORE INTO voter_eligibility (election_id, user_id, has_voted)
SELECT e.id, u.id, 0
FROM elections e JOIN users u ON u.role = 'voter' AND u.status = 'active'
WHERE e.title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
);
-- Verification: this batch should contain 50 elections, 250 positions, 750 active candidates.
SELECT COUNT(*) AS election_count, MIN(start_datetime) AS first_opening, MAX(end_datetime) AS last_closing
FROM elections WHERE title IN (
    'Campus Safety Council Election 2026',
    'Student Technology Council Election 2026',
    'Library Services Committee Election 2026',
    'Residence Hall Advisory Council Election 2026',
    'Wellness Ambassadors Board Election 2026',
    'Student Research Council Election 2026',
    'Community Service Council Election 2026',
    'Arts Program Council Election 2026',
    'Sports Facilities Committee Election 2026',
    'Sustainability Projects Board Election 2026',
    'Student Dining Council Election 2026',
    'Accessibility Advisory Board Election 2026',
    'First Year Mentorship Council Election 2026',
    'Graduate Engagement Committee Election 2026',
    'International Programs Council Election 2026',
    'Student Media Council Election 2026',
    'Learning Resources Committee Election 2026',
    'Student Events Council Election 2026',
    'Career Development Council Election 2026',
    'Campus Mobility Council Election 2026',
    'Volunteer Programs Board Election 2026',
    'Student Finance Advisory Council Election 2026',
    'Cultural Exchange Council Election 2026',
    'Academic Support Council Election 2026',
    'Student Leadership Council Election 2026',
    'Design and Innovation Council Election 2026',
    'Recreation Programs Board Election 2026',
    'Student Housing Advisory Council Election 2026',
    'Campus Green Spaces Council Election 2026',
    'Peer Support Council Election 2026',
    'Student Health Advocacy Council Election 2026',
    'Digital Campus Committee Election 2026',
    'University Traditions Council Election 2026',
    'Entrepreneurship Student Council Election 2026',
    'Creative Arts Assembly Election 2026',
    'Student Rights Council Election 2026',
    'Campus Communications Board Election 2026',
    'Public Service Student Council Election 2026',
    'Science Student Society Election 2026',
    'Humanities Student Society Election 2026',
    'Business Student Society Election 2026',
    'Engineering Student Society Election 2026',
    'Education Student Society Election 2026',
    'Health Professions Student Society Election 2026',
    'Computing Student Society Election 2026',
    'Music and Performance Council Election 2026',
    'Library Outreach Council Election 2026',
    'Local Partnerships Council Election 2026',
    'Student Wellbeing Assembly Election 2026',
    'Year End Student Forum Election 2026'
);
