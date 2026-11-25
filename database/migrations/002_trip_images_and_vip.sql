-- Apply once to existing installations running MariaDB.
ALTER TABLE trips
  ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL AFTER vehicle_description,
  ADD COLUMN IF NOT EXISTS trip_class ENUM('regular','vip') NOT NULL DEFAULT 'regular' AFTER image_path;
