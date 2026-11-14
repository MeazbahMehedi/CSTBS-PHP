<?php
declare(strict_types=1);

function upgrade_database(): void
{
    // MariaDB's additive migration supports installations created with version 1.
    query('ALTER TABLE events ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL');
    query('INSERT IGNORE INTO schema_versions(version) VALUES(2)');
}
