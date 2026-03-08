-- Add location column to services table
ALTER TABLE services ADD COLUMN location VARCHAR(100) DEFAULT 'All Locations';

-- Update some initial logic for locations (random allocation of New Delhi, Mumbai, Bangalore)
UPDATE services SET location = 'New Delhi' WHERE id = 1;
UPDATE services SET location = 'Mumbai' WHERE id = 2;
UPDATE services SET location = 'Bangalore' WHERE id = 3;
UPDATE services SET location = 'New Delhi' WHERE id = 4;
