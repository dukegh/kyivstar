# PHP класс для работы с web интерфейсом Мій Київстар

## Пример использования

```php
include "Kyivstar.php";

$kyivstar = new Kyivstar();
$kyivstar->login('380671234567', 'mysecretkey');
$balance = $kyivstar->getBalance();
echo "balance: $balance\n";
```
