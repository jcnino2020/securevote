# SecureVote v1

Early student-portal interface at `/securevote/v1/`: welcome, account sign-in/dashboard, election directory, and candidate profiles. No completion labels or public roadmap.

Authentication uses the existing parent SecureVote bootstrap, account database, password verification, status checks, throttling, session rotation, and audit logging. It shares the main SecureVote login session; signing out here signs out that session. Registration and Google authentication are outside this version’s interface. Election and candidate content remains sample data, and ballots cannot be submitted here.
