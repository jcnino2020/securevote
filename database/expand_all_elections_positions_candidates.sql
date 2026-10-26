-- Idempotent expansion for every election in the current SecureVote database.
-- Each election ends with at least five positions and each position with at least
-- three active sample candidates. Existing positions, candidates, ballots,
-- votes, election status, and voting windows are preserved.
-- Positions added after ballots were cast have zero historical selections.
SET time_zone = '+08:00';
START TRANSACTION;

-- Add Chairperson only while an election has fewer than five positions.
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Chairperson', 'Coordinates the council or committee.', 1,
       COALESCE((SELECT MAX(p2.display_order) FROM positions p2 WHERE p2.election_id = e.id), 0) + 1
FROM elections e
WHERE (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) < 5
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Chairperson');

-- Add Vice Chairperson only while an election has fewer than five positions.
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Vice Chairperson', 'Supports the chairperson and student initiatives.', 1,
       COALESCE((SELECT MAX(p2.display_order) FROM positions p2 WHERE p2.election_id = e.id), 0) + 1
FROM elections e
WHERE (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) < 5
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Vice Chairperson');

-- Add Secretary only while an election has fewer than five positions.
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Secretary', 'Maintains meeting records and announcements.', 1,
       COALESCE((SELECT MAX(p2.display_order) FROM positions p2 WHERE p2.election_id = e.id), 0) + 1
FROM elections e
WHERE (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) < 5
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Secretary');

-- Add Treasurer only while an election has fewer than five positions.
INSERT INTO positions (election_id, name, description, max_selections, display_order)
SELECT e.id, 'Treasurer', 'Oversees student activity funds and reporting.', 1,
       COALESCE((SELECT MAX(p2.display_order) FROM positions p2 WHERE p2.election_id = e.id), 0) + 1
FROM elections e
WHERE (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) < 5
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.election_id = e.id AND p.name = 'Treasurer');

-- Fill candidate slot 1 where needed.
INSERT INTO candidates (election_id, position_id, first_name, last_name, party, biography, platform, status)
SELECT e.id, p.id, ELT(1 + MOD(p.id * 3 + 1 * 11, 30), 'Aira', 'Noel', 'Mika', 'Jonah', 'Lea', 'Rafael', 'Sofia', 'Nico', 'Isabel', 'Marco', 'Trisha', 'Paolo', 'Mara', 'Kenji', 'Dana', 'Luis', 'Aya', 'Miguel', 'Tala', 'Enzo', 'Rina', 'Carlo', 'Bea', 'Andre', 'Lia', 'Jared', 'Nina', 'Sam', 'Celine', 'Diego'), ELT(1 + MOD(e.id * 7 + p.id * 3 + 1 * 13, 30), 'Cruz', 'Reyes', 'Santos', 'Mendoza', 'Tan', 'Garcia', 'Lim', 'Chua', 'Ramos', 'Dela Cruz', 'Navarro', 'Bautista', 'Flores', 'Villanueva', 'Castillo', 'Aquino', 'Pascual', 'Torres', 'Mercado', 'Rivera', 'De Leon', 'Sy', 'Gonzales', 'Salazar', 'Ocampo', 'Valdez', 'Diaz', 'Lopez', 'Soriano', 'Manalo'), 'Students First', NULL, NULL, 'active'
FROM positions p
JOIN elections e ON e.id = p.election_id
WHERE (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id AND c.status = 'active') < 1;

-- Fill candidate slot 2 where needed.
INSERT INTO candidates (election_id, position_id, first_name, last_name, party, biography, platform, status)
SELECT e.id, p.id, ELT(1 + MOD(p.id * 3 + 2 * 11, 30), 'Aira', 'Noel', 'Mika', 'Jonah', 'Lea', 'Rafael', 'Sofia', 'Nico', 'Isabel', 'Marco', 'Trisha', 'Paolo', 'Mara', 'Kenji', 'Dana', 'Luis', 'Aya', 'Miguel', 'Tala', 'Enzo', 'Rina', 'Carlo', 'Bea', 'Andre', 'Lia', 'Jared', 'Nina', 'Sam', 'Celine', 'Diego'), ELT(1 + MOD(e.id * 7 + p.id * 3 + 2 * 13, 30), 'Cruz', 'Reyes', 'Santos', 'Mendoza', 'Tan', 'Garcia', 'Lim', 'Chua', 'Ramos', 'Dela Cruz', 'Navarro', 'Bautista', 'Flores', 'Villanueva', 'Castillo', 'Aquino', 'Pascual', 'Torres', 'Mercado', 'Rivera', 'De Leon', 'Sy', 'Gonzales', 'Salazar', 'Ocampo', 'Valdez', 'Diaz', 'Lopez', 'Soriano', 'Manalo'), 'Campus Voice', NULL, NULL, 'active'
FROM positions p
JOIN elections e ON e.id = p.election_id
WHERE (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id AND c.status = 'active') < 2;

-- Fill candidate slot 3 where needed.
INSERT INTO candidates (election_id, position_id, first_name, last_name, party, biography, platform, status)
SELECT e.id, p.id, ELT(1 + MOD(p.id * 3 + 3 * 11, 30), 'Aira', 'Noel', 'Mika', 'Jonah', 'Lea', 'Rafael', 'Sofia', 'Nico', 'Isabel', 'Marco', 'Trisha', 'Paolo', 'Mara', 'Kenji', 'Dana', 'Luis', 'Aya', 'Miguel', 'Tala', 'Enzo', 'Rina', 'Carlo', 'Bea', 'Andre', 'Lia', 'Jared', 'Nina', 'Sam', 'Celine', 'Diego'), ELT(1 + MOD(e.id * 7 + p.id * 3 + 3 * 13, 30), 'Cruz', 'Reyes', 'Santos', 'Mendoza', 'Tan', 'Garcia', 'Lim', 'Chua', 'Ramos', 'Dela Cruz', 'Navarro', 'Bautista', 'Flores', 'Villanueva', 'Castillo', 'Aquino', 'Pascual', 'Torres', 'Mercado', 'Rivera', 'De Leon', 'Sy', 'Gonzales', 'Salazar', 'Ocampo', 'Valdez', 'Diaz', 'Lopez', 'Soriano', 'Manalo'), 'Independent', NULL, NULL, 'active'
FROM positions p
JOIN elections e ON e.id = p.election_id
WHERE (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id AND c.status = 'active') < 3;

COMMIT;

-- Verification: every election should report at least 5 positions and every
-- position should report at least 3 active candidates.
SELECT MIN(position_count) AS minimum_positions FROM (
  SELECT COUNT(*) AS position_count FROM positions GROUP BY election_id
) AS election_positions;
SELECT MIN(candidate_count) AS minimum_active_candidates FROM (
  SELECT COUNT(*) AS candidate_count FROM candidates WHERE status = 'active' GROUP BY position_id
) AS position_candidates;
