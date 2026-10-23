-- Run once with a database operator account; change 'user'/'%' to your app account.
-- Escaped underscores are literal characters. Only generated tenant database names match.
GRANT ALL PRIVILEGES ON `ec\_tenant\_%`.* TO 'user'@'%';
-- The control/default database keeps the grants already required by the application.
-- Do not grant global privileges or use a root account in DATABASE_URL.
