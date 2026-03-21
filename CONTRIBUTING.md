# Contributing to sadad-php-sdk

Thank you for your interest in contributing to this project!

## How to Contribute

### Reporting Issues
Before reporting an issue, please check if it already exists in the issue tracker. When reporting a bug, use the provided bug report template and include as much detail as possible.

### Submitting Changes

1. **Fork** the repository on GitHub
2. **Clone** your fork locally:
   ```bash
   git clone https://github.com/your-username/sadad-php-sdk.git
   cd sadad-php-sdk
   ```
3. **Create a branch** for your changes:
   ```bash
   git checkout -b feature/my-new-feature
   ```
   Use descriptive branch names such as `feature/add-recurring-payments` or `fix/signature-validation-bug`.

4. **Install dependencies**:
   ```bash
   composer install
   ```

5. **Make your changes** following the existing code style and conventions.

6. **Write tests** for your changes. All new functionality must include unit tests.

7. **Run the test suite** and ensure all tests pass:
   ```bash
   composer test
   ```

8. **Commit your changes** with a clear, descriptive commit message:
   ```bash
   git commit -m "Add support for recurring invoice payments"
   ```

9. **Push** to your fork:
   ```bash
   git push origin feature/my-new-feature
   ```

10. **Open a Pull Request** against the `main` branch with a clear title and description of what your changes do and why.

## Code Standards

- Follow PSR-12 coding standards
- Use PHP 8.1+ features where appropriate
- Write clear, self-documenting code with docblocks for public methods
- Keep methods focused and single-purpose
- Handle exceptions gracefully using the provided exception hierarchy

## Testing

All contributions must include appropriate test coverage. Tests live in the `tests/Unit/` directory and follow the same namespace structure as the source.

Run tests with:
```bash
composer test
```

## Questions

If you have questions or need clarification on anything, feel free to reach out at info@louis-innovations.com.
