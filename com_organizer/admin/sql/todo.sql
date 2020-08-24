# region planning data migrated

#drop methodid fk, index & column from units

# remove events which are not associated with instances
DELETE
FROM `v7ocf_organizer_events`
WHERE `id` NOT IN (SELECT DISTINCT `eventID`
                   FROM `v7ocf_organizer_instances`);

#endregion

#region course data migrated

#drop campusid fk, index & column
ALTER TABLE `v7ocf_organizer_units`
    DROP COLUMN `fee`,
    DROP COLUMN `maxParticipants`,
    DROP COLUMN `registrationType`;

#endregion

#region after merging events

ALTER TABLE `v7ocf_organizer_events` ADD UNIQUE INDEX `entry` (`code`, `organizationID`);

#endregion

#revisit foreign keys as to which truly need to be deleted on cascade