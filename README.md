# Hawk Symfony

Symfony errors Catcher for [Hawk.so](https://hawk.so).

## Setup

1. [Register](https://garage.hawk.so/sign-up) an account, create a Project and get an Integration Token.

2. Install SDK via [composer](https://getcomposer.org) to install the Catcher

Catcher provides support for PHP 7.2 or later

```bash
$ composer require codex-team/hawk.symfony
```

### Configuration

Add the following authorization information to your `.env` file:

```env
HAWK_TOKEN=<your_token_from_the_control_panel>
```

Create a configuration file at `config/packages/hawk.yaml` with the following content:

```yaml
hawk:
  integration_token: '%env(HAWK_TOKEN)%'
```

In the `config/packages/monolog.yaml` file, specify the handler settings under the appropriate section (`dev` or `prod`):

```yaml
hawk:
  type: service
  id: HawkBundle\Monolog\Handler
  level: error
```

### Adding User Information to Error Reports:

```php
$this->catcher->setUser([
    'name' => 'user name',
    'photo' => 'user photo',
]);

$this->catcher->setContext([
    // Additional context information
]);
```

### Sending Exceptions Manually:
To manually send exceptions, initialize `__construct(\HawkBundle\Catcher $catcher)` class via dependency injection (DI), and use the following method:

```php
$this->catcher->sendException($exception);
```

### Sending Custom Messages:

You can also send custom messages using the `->sendMessage(...)` method:

```php
$this->catcher->sendMessage(
    'your message', 
    [
        // Additional context information
    ]
);
```

### Example: Sending Manually

```php
private $catcher;

public function __construct(\HawkBundle\Catcher $catcher) 
{
    $this->catcher = $catcher;
}

public function test()
{
    try {
        // The code where you need to catch the error
    } catch (\Exception $exception) {
        $this->catcher->sendException($exception);
    }
}
```

## Issues and improvements

Feel free to ask questions or improve the project.

## Links

Repository: https://github.com/codex-team/hawk.symfony

Report a bug: https://github.com/codex-team/hawk.symfony/issues

Composer Package: https://packagist.org/packages/codex-team/hawk.symfony

CodeX Team: https://codex.so

## License

MIT
