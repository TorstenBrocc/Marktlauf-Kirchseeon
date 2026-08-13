ALTER TABLE sponsors
    ADD COLUMN foerderprogramm TEXT         NULL AFTER branche,
    ADD COLUMN kontaktweg      TEXT         NULL AFTER foerderprogramm,
    ADD COLUMN website         VARCHAR(255) NULL AFTER kontaktweg,
    ADD COLUMN quellenurl      VARCHAR(500) NULL AFTER website;
