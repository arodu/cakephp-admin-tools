# AdminTools plugin for CakePHP

Small admin-tools plugin for CakePHP 5. Currently it provides the
`backup_db` command: dumps a database (MySQL, PostgreSQL, SQLite or SQL Server),
optionally compresses it (gzip / zip / rar), and optionally emails the backup
file.

## Requirements

- PHP >= 8.2
- `cakephp/cakephp` ^5.0

## Installation

```
composer require arodu/cakephp-admin-tools:^2.0
```

Configure it in `config/admin_tools.php` (see the plugin's config file for all
options).

## Usage

```
bin/cake backup_db
```

Options: `-d/--datasources`, `-c/--compress` (`gzip|zip|rar|none`),
`-n/--name`, `--path`, `--emailList`, `--emailDisabled`, `--emailConfig`,
`--disableRemoveFile`.

The command is gated by `AdminTools.backupDb.enabled` (env `AT_BACKUP_ENABLED`);
when disabled it just prints a warning and exits.

## License

MIT
