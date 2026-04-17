# Contributing

Contributions are welcome! Here's how to get involved.

## Prerequisites

- PHP 8.3+
- Composer

## Installation

```bash
git clone https://github.com/mkakpabla/sms-gateway.git
cd sms-gateway
composer install
```

## Workflow

1. Fork the repository
2. Create a branch for your feature (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -m 'Add my feature'`)
4. Push the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

## Tests

Run the tests before submitting a PR:

```bash
composer test
```

## Code Quality

The project uses PHPStan, PHPMD and PHP_CodeSniffer. Make sure your code passes all checks:

```bash
composer quality
```

You can also run them individually:

```bash
composer phpstan
composer phpmd
composer phpcs
```

To automatically fix code style issues:

```bash
composer phpcbf
```

## Adding an SMS Driver

1. Create a class in `src/Drivers/` that implements `SmsDriverInterface`
2. Add corresponding tests in `tests/`
3. Document the driver in the README

## Conventions

- Follow the existing code style (PSR-12)
- Write tests for any new feature or bug fix
- Keep PRs focused on a single topic

## Reporting a Bug

Open an [issue](https://github.com/mkakpabla/sms-gateway/issues) describing:

- The expected behavior
- The actual behavior
- Steps to reproduce the problem
- Your PHP and package version

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
