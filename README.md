# Fastchat [BETA]
A quick, anonymous and secure chat app made with Htmx and php.

## Features
- Chatrooms: Users can share link to chatroom and create semi custom room name's (Must be 13 characters long & only Letters and Numbers).
The chatroom have join & leave messages for users and a list of active users in the left bar with afk detection.

- Chat: The input is timed to stop spam and supports limited html format options (b, i) and more.

- Emojis: Users can select and send emojis from the menu.

- Security: The forms use CSRF protection, message encryption, SSL(if enabled) and strong security in html and php. Its recommended to add a Content-Security-Policy for the whole app (like in index.php).

## Images
![start](https://github.com/user-attachments/assets/bfbfd5eb-b231-4bbe-8843-787c034ee485)
![chat](https://github.com/user-attachments/assets/afbbcc58-7b79-4b9b-8aa9-2bd284173b5b)
![chatmore](https://github.com/user-attachments/assets/8a047feb-bbdf-4f33-9ee0-4a4d0d8fed76)

## Prerequisites
- PHP installed (Developed on v8.0+)
- A web server (e.g. Apache, Nginx)
- A database (Currently set up with SQLite3)

## Configuration
To configure follow these steps:
1. Open the `php/config.php` file located in the project directory.
2. Update the database connection settings with your own database credentials.
3. Customize other settings such as:
- `$servername`
- `$db_messages_name & $db_users_name`
- `$debugging`

## License
Fastchat is released under the GNU Affero General Public License. See the `LICENSE` file for more information.
