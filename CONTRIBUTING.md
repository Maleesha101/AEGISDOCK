# AegisDock Contributing Guide

## Development Workflow

1. **Fork the repository** and create a branch from `main`:
   ```bash
   git checkout -b feature/short-description
   ```

2. **Make your changes** following the project's conventions:
   - Use conventional commit messages
   - Add or update documentation as needed
   - Include tests for new functionality
   - Ensure existing tests pass

3. **Run the test suite** to verify your changes:
   ```bash
   cd api && ./vendor/bin/phpunit
   ```

4. **Commit your changes** using conventional commits:
   ```bash
   git commit -m "type: description of changes"
   ```

5. **Push to your fork** and create a Pull Request

## Conventional Commit Messages

This project uses conventional commit format:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

### Types
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Formatting, missing semicolons, etc.
- `refactor`: Code refactoring
- `test`: Adding missing tests
- `chore`: Maintenance tasks

### Example
```bash
git commit -m "feat(crypto): add AES-GCM secure implementation"
git commit -m "fix(security): add Content-Security-Policy header"
git commit -m "docs(vulnerability): add XSS patterns to inventory"
```

## Code Style

### PHP
- Follow PSR-12 coding standard
- Use `php-cs-fixer` for code formatting
- Type declarations on all public methods

### JavaScript/TypeScript
- Use ESLint with Airbnb base config
- Prefer const/let over var
- Functional components with hooks

## Branch Naming

- Features: `feature/short-description`
- Bug fixes: `fix/short-description`
- Documentation: `docs/short-description`
- Hotfixes: `hotfix/short-description`

## Pull Request Process

1. Ensure all tests pass
2. Link the PR to any related issues
3. Fill in the PR template
4. Address review feedback promptly
5. Squash merge upon approval

## License

By contributing to AegisDock, you agree that your contributions will be licensed under the MIT License.