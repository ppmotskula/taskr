-- Create the database schema

CREATE TABLE users (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT,
    email TEXT,
    email_tmp TEXT,
    tz_diff INTEGER,
    added INTEGER,
    pro_until INTEGER,
    credits INTEGER
);
create index user_username on users (username);

CREATE TABLE tasks (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    project_id INTEGER,
    title TEXT NOT NULL,
    scrap TEXT,
    liveline INTEGER,
    deadline INTEGER,
    added INTEGER,
    last_started INTEGER,
    last_stopped INTEGER,
    finished BOOLEAN,
    archived BOOLEAN,
    duration INTEGER
);

CREATE TABLE projects (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    finished INTEGER,
    archived BOOLEAN
);
