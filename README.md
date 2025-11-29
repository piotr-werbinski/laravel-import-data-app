# Laravel + Vue.js Project

Projekt Laravel z frontendem w Vue.js i Tailwind CSS. Poniżej znajduje się lista plików, które zostały utworzone lub zmodyfikowane w tym projekcie opartym o pusty Starter Kit Laravela.

## Migracje

- `database/migrations/create_transactions_table.php` – migracja tabeli `transactions`.
- `database/migrations/create_imports_table.php` – migracja tabeli `imports`.
- `database/migrations/create_import_logs_table.php` – migracja tabeli `import_logs`.

## Modele

- `app/Models/Import.php` – model dla tabeli `imports`.
- `app/Models/ImportLog.php` – model dla tabeli `import_logs`.
- `app/Models/Transaction.php` – model dla tabeli `transactions`.

## Kontrolery

- `app/Http/Controllers/ImportController.php` – kontroler obsługujący logikę importu.

## Routing

- `routes/api.php` – definicje tras API.
- `app/Providers/RouteServiceProvider.php` – dostosowanie providerów tras w Laravelu.

## Cache / Konfiguracja

- `bootstrap/cache/app.php` – zmodyfikowany plik cache aplikacji.

## Frontend

- `resources/views/welcome.blade.php` – zmodyfikowany widok powitalny.
- `resources/js/app.js` – główny plik JS dla Vue.js.
- `vite.config.js` – konfiguracja Vite.
- `resources/js/components/ImportApp.vue` – komponent Vue dla importów.