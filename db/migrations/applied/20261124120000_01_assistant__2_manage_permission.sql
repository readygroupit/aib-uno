-- "Configurare gli agenti": accendere e spegnere le regole, i giorni, cosa fanno da soli, i periodi forti.
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT 1, 'assistant', 'Agenti', 'Gli agenti dell''assistente', 0, 1, NOW(), 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permission_groups WHERE code = 'assistant' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'assistant.manage', 'Configurare gli agenti', 'Accendere o spegnere le regole degli agenti, i giorni, cosa fanno da soli, i periodi forti.', 'edit', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'assistant' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'assistant.manage' AND status = 1);
INSERT IGNORE INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT pr.id, pe.id, 1, NOW(), 1 FROM profiles pr JOIN permissions pe
WHERE pr.status = 1 AND pe.status = 1 AND pe.code = 'assistant.manage'
  AND pr.name IN ('Amministratore', 'Direzione', 'Team leader e amministrazione');
