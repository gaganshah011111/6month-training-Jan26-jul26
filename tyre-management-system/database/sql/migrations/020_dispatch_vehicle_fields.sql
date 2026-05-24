-- Vehicle insurance / RC fields for logistics master
ALTER TABLE dispatch_vehicles
    ADD COLUMN IF NOT EXISTS insurance_expiry DATE NULL AFTER capacity,
    ADD COLUMN IF NOT EXISTS rc_number VARCHAR(60) NULL AFTER insurance_expiry;
