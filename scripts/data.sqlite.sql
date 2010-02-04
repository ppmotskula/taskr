-- Populate the database with sample data

INSERT INTO users (id, username) VALUES (
    1, 'anonymous'
);

INSERT INTO tasks (user_id, title, scrap,
    last_started, last_stopped, finished, archived) VALUES (
    1, 'Learn about TaskHammer',
    'TaskHammer is a time manager that lets you focus automatically on what is important when it''s important. Instead of requiring you to assign (and, when things change, reassign or ignore) priority flags to each task, it relies upon your ability to instinctively focus on the "right" things to do.

Here are a couple of URLs that should be rendered:
 * http://taskhammer.com/
 * taskhammer.com

And yes, that''s a multiline scrap. Go figure.',
    1, 0, 0, 0
);

INSERT INTO tasks (user_id, title, scrap,
    last_started, last_stopped, finished, archived) VALUES (
    1, 'Try it out',
    'That''s your first upcoming task. You can start using TaskHammer anonymously, but you must be signed in if you want to save your status, and you can''t sign in unless you have signed up before.',
    0, 1, 0, 0
);

INSERT INTO tasks (user_id, title, scrap,
    last_started, last_stopped, finished, archived) VALUES (
    1, 'Sign up or sign in',
    'Do it at http://taskhammer.com/login/. Don''t worry, it doesn''t cost you anything.',
    0, 0, 0, 0
);

INSERT INTO tasks (user_id, title, scrap,
    last_started, last_stopped, finished, archived) VALUES (
    1, 'Visit taskhammer.com',
    'That''s something you''ve already done. Isn''t it obvious?',
    0, 1, 1, 0
);

INSERT INTO tasks (user_id, title, scrap,
    last_started, last_stopped, finished, archived) VALUES (
    1, 'Go online',
    'That''s something you''ve done and archived.',
    0, 1, 1, 1
);

INSERT INTO users (id, username, password) VALUES (
    2, 'user', 'c884d29b5a69df9282d4a1ed3fbfd818643b62ebc095585b'
);
