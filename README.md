# AdminTools plugin for CakePHP

Small admin-tools plugin for CakePHP 5. Currently it provides the `backup_db`
command: it dumps a database (MySQL, PostgreSQL, SQLite or SQL Server),
optionally compresses the dump (gzip / zip / rar) and optionally emails the
resulting file.

## Requirements

- PHP >= 8.2
- `cakephp/cakephp` ^5.0

## Installation

```
composer require arodu/cakephp-admin-tools:^2.0
```

Once the plugin is loaded the `backup_db` command is registered automatically.
Configure it in your app's `config/admin_tools.php` (copy the plugin's
`config/admin_tools.php` as a starting point).

## Configuration

Main options (env vars in parentheses), all under `AdminTools.backupDb`:

| Option | Env | Default |
|--------|-----|---------|
| `enabled` | `AT_BACKUP_ENABLED` | `true` |
| `datasources` | `AT_BACKUP_DATASOURCES` | `default` |
| `compress` | `AT_BACKUP_COMPRESS` | `gzip` |
| `name` | `AT_BACKUP_NAME` | `backup-name` |
| `path` | `AT_BACKUP_PATH` | `TMP` |
| `fileTemplate` | `AT_BACKUP_FILE_TEMPLATE` | `:name_:datasource_:datetime` |
| `removeFile` | `AT_BACKUP_REMOVE_FILE` | `true` |

Email options live under `AdminTools.backupEmail.*` (`enabled`, `config`,
`format`, `emailList`, `subject`, `template`, `layout`).

## Usage

```
bin/cake backup_db [options]
```

| Option | Short | Description |
|--------|-------|-------------|
| `--datasources` | `-d` | Datasources to back up, comma separated (e.g. `default,reports`). |
| `--compress` | `-c` | Compression method: `gzip`, `zip`, `rar` or `none`. |
| `--name` | `-n` | Base name used to build the file name. |
| `--path` | | Directory where the backup file is written. |
| `--disableRemoveFile` | | Keep the backup file instead of deleting it afterwards. |
| `--emailList` | | Comma-separated recipients for the backup email. |
| `--emailDisabled` | | Do not send the email, even if configured. |
| `--emailConfig` | | Mailer config name to use when sending the email. |

### Examples

```bash
# Back up the configured datasource(s) (gzip + email, as per config)
bin/cake backup_db

# Back up two datasources, zipped, and keep the file on disk without emailing
bin/cake backup_db -d default,reports -c zip --disableRemoveFile

# Email the backup to specific recipients
bin/cake backup_db --emailList ops@example.com,dev@example.com

# Write the backup to a custom directory
bin/cake backup_db --path /var/backups
```

If `AdminTools.backupDb.enabled` is `false`, the command prints a warning and
exits without doing anything.

## License

MIT
