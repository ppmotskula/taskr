-- Create the database schema

CREATE TABLE users (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT,
    email TEXT,
    tz_diff INTEGER,
    added INTEGER,
    pro_until INTEGER,
    credits INTEGER
);
CREATE INDEX user_id on users (id);
create index user_username on users (username);

CREATE TABLE tasks (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    project_id INTEGER,
    title TEXT NOT NULL,
    scrap TEXT,
    deadline INTEGER,
    added INTEGER,
    last_started INTEGER,
    last_stopped INTEGER,
    finished BOOLEAN,
    archived BOOLEAN,
    duration INTEGER
);
CREATE INDEX task_id on tasks (id);

CREATE TABLE projects (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    finished BOOLEAN,
    archived BOOLEAN
);
CREATE INDEX project_id on projects (id);
