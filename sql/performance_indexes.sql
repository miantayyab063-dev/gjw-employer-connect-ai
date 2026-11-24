-- Optional indexes for faster dashboard, CRM and recent activity queries.
-- Run once on existing database; skip indexes that already exist.
ALTER TABLE companies ADD INDEX idx_company_created (id, country, status);
ALTER TABLE contacts ADD INDEX idx_contact_company_created (company_id, id);
ALTER TABLE proposals ADD INDEX idx_proposal_status_created (status, id);
ALTER TABLE activities ADD INDEX idx_activity_followup (next_action, id);
ALTER TABLE discovery_runs ADD INDEX idx_discovery_created (created_at);
ALTER TABLE mail_events ADD INDEX idx_mail_event_proposal (proposal_id, created_at);
