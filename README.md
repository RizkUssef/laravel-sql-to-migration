# **Laravel SQL to Migration**

**Laravel SQL to Migration** is a Laravel package that automatically converts raw SQL `CREATE TABLE` statements into fully functional **Laravel migration files**.

---

## ✅ **Features**

- ✔ Parse **multiple tables** from a single SQL file
- ✔ Supports **column types**: `INT`, `BIGINT`, `VARCHAR`, `TEXT`, `DECIMAL`, `TIMESTAMP`, etc.
- ✔ Detect **auto-increment** and convert to `bigIncrements()`
- ✔ Add **primary keys**, **foreign keys**, and **indexes**
- ✔ Handles:
    - `nullable()`
    - `unique()`
    - `default()`
- ✔ Supports **DECIMAL precision** (e.g., `DECIMAL(8,2)`)
- ✔ Optional `timestamps()` via `--timestamps` flag
- ✔ Generates Laravel migration files inside `database/migrations`
---
## 📦 **Installation**

```bash
composer require rizkussef/laravel-sql-to-migration
```

---

## 🚀 **Usage**

### **1. Place your SQL file**

Example:

```pgsql
database/schema.sql
```

With content:
```sql
CREATE TABLE IF NOT EXISTS `orders` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `price` DECIMAL(8,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  INDEX `product_idx` (`product_name`),
  CONSTRAINT `fk_orders_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
```

---

### **2. Run the command**

bash
```bash
php artisan sql:sql-to-migration database/schema.sql --timestamps
```


---

### ✅ **Generated Migration**
```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('product_name', 255);
    $table->decimal('price', 8, 2)->default('0.00');
    $table->timestamps();

    // Indexes
    $table->index(['product_name'], 'product_idx');

    // Foreign Keys
    $table->foreign('user_id', 'fk_orders_users')
        ->references('id')
        ->on('users')
        ->onDelete('cascade')
        ->onUpdate('cascade');
});

```

---

## ⚙ **Options**

|Option|Description|
|---|---|
|`--timestamps`|Adds `created_at` & `updated_at` columns|

---

## ✅ **Supported**

✔ Laravel 9.x & 10.x  
✔ PHP 8.0+

---

## 🔥 **When to Use**

- Migrating **legacy SQL schema** to Laravel
- Converting **raw SQL dumps** to Laravel migrations
- Automating **third-party database setup** in Laravel projects

---

## 📌 **Roadmap**

- ✅ Support `ON DELETE` / `ON UPDATE` cascade    
- ✅ Handle composite primary keys

---

## 📄 **License**

MIT © [Rizk Ussef](https://github.com/rizkussef)