-- Publish existing SecureVote elections' aggregate tallies after a voter submits.
-- Closed elections retain final public results. No ballots or votes are changed.
UPDATE elections
   SET results_visibility = 'after_vote'
 WHERE results_visibility <> 'after_vote';
