CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    expired_at BIGINT DEFAULT 0,
    is_confirmed BOOLEAN NOT NULL DEFAULT false,
    is_checked BOOLEAN NOT NULL DEFAULT false,
    is_valid BOOLEAN NOT NULL DEFAULT false
);

CREATE TYPE queue_check AS ENUM('check_required', 'check_valid', 'check_invalid');
CREATE TABLE IF NOT EXISTS queue (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id BIGINT UNIQUE NOT NULL,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    sent_at BIGINT NOT NULL,
    status queue_check NOT NULL DEFAULT 'check_required'
);

CREATE OR REPLACE PROCEDURE make_fixtures(size BIGINT) LANGUAGE plpgsql AS $fixtures$
    DECLARE
        start_time TIMESTAMP;
        dont_have_subscription_count FLOAT;
        confirmed_emails_count FLOAT;
    BEGIN
        RAISE NOTICE 'Make fixtures: % records', size;

        start_time := clock_timestamp();

        INSERT INTO users (id, username, email, expired_at, is_confirmed) VALUES (
            generate_series(1, size),
            md5(random()::TEXT),
            md5(random()::TEXT) || '@gmail.com',
            CASE WHEN random() > 0.8 THEN extract(EPOCH FROM now() + random() * INTERVAL '5' day) ELSE 0 END,
            random() > 0.85
        );

        dont_have_subscription_count := count(id) FROM users WHERE expired_at = 0;
        confirmed_emails_count := count(id) FROM users WHERE is_confirmed = true;

        RAISE NOTICE E'Don\'t have a subscriptions: % %%', dont_have_subscription_count / size * 100;
        RAISE NOTICE 'Confirmed emails: % %%', confirmed_emails_count / size * 100;
        RAISE NOTICE '% records added in % seconds', size, extract(EPOCH FROM clock_timestamp() - start_time);
    END;
$fixtures$;

CALL make_fixtures(5000000);
VACUUM ANALYSE users;

ALTER TABLE queue ADD CONSTRAINT queue_fk_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

CREATE INDEX users_idx_expired_at ON users (expired_at);
CREATE INDEX queue_idx_sent_at ON queue (sent_at);