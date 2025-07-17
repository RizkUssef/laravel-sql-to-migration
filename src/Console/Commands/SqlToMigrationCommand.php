<?php 

namespace Rizkussef\LaravelSqlToMigration\Console\Commands;

use Exception;
use Illuminate\Console\Command;
class SqlToMigrationCommand extends Command{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sql:sql-to-migration {file} {--timestamps}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sqlFilePath = $this->argument('file');
        $withTimestamps = $this->option('timestamps');

        if (!file_exists($sqlFilePath)) {
            throw new Exception("SQL file not found: $sqlFilePath");
        }

        $sql = file_get_contents($sqlFilePath);
        $sql = preg_replace('/\s+/', ' ', $sql); // Normalize spaces

        preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?\s*\((.*?)\)\s*(ENGINE|;)/is', $sql, $tables, PREG_SET_ORDER);

        if (empty($tables)) {
            throw new Exception("No CREATE TABLE statements found in the SQL file.");
        }

        $outputDir = base_path('database/migrations');

        foreach ($tables as $tableMatch) {
            $tableName = $tableMatch[1];
            $columnsPart = $tableMatch[2];
            $columns = preg_split('/,(?![^(]*\))/', $columnsPart);

            $migration = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\n";
            $migration .= "return new class extends Migration\n{\n    public function up()\n    {\n";
            $migration .= "        Schema::create('$tableName', function (Blueprint \$table) {\n";

            $foreignKeys = [];
            $indexes = [];

            foreach ($columns as $col) {
                $col = trim($col);
                // FOREIGN KEY or CONSTRAINT
                if (stripos(trim($col), 'FOREIGN KEY') === 0 || stripos($col, 'CONSTRAINT') === 0) {
                    // preg_match('/CONSTRAINT\s+`?(\w+)?`?\s*FOREIGN KEY\s*\(`(\w+)`\)\s*REFERENCES\s*`(\w+)`\s*\(`(\w+)`\)(.*?)$/i', $col, $fkMatch);
                    preg_match('/(CONSTRAINT\s+`?(\w+)?`?\s*)?FOREIGN KEY\s*\((`?\w+`?(?:,\s*`?\w+`?)*)\)\s*REFERENCES\s*`(\w+)`\s*\((`?\w+`?(?:,\s*`?\w+`?)*)\)(.*?)$/i', $col, $fkMatch);
                    if (count($fkMatch) >= 5) {
                        $fkName = $fkMatch[2] ?? null;
                        $localCols = array_map(fn($c) => trim($c, '` '), explode(',', $fkMatch[3]));
                        $refCols = array_map(fn($c) => trim($c, '` '), explode(',', $fkMatch[5]));
                        $onDelete = stripos($fkMatch[6], 'ON DELETE CASCADE') !== false ? 'cascade' : "NO ACTION";
                        $onUpdate = stripos($fkMatch[6], 'ON UPDATE CASCADE') !== false ? 'cascade' : "NO ACTION";

                        $foreignKeys[] = [
                            'name' => $fkName,
                            'columns' => $localCols,
                            'references' => $refCols,
                            'on' => $fkMatch[4],
                            'onDelete' => $onDelete,
                            'onUpdate' => $onUpdate
                        ];
                    }
                    continue;
                }

                // INDEX or UNIQUE INDEX
                if (stripos($col, 'INDEX') === 0 || stripos($col, 'KEY') === 0 || stripos($col, 'UNIQUE INDEX') === 0) {
                    preg_match('/(UNIQUE\s+)?(?:INDEX|KEY)\s+`(\w+)`\s*\(([^)]+)\)/i', $col, $idxMatch);
                    if (count($idxMatch) >= 4) {
                        $columns = array_map(function ($c) {
                            return trim(str_replace(['`', 'ASC', 'DESC'], '', $c));
                        }, explode(',', $idxMatch[3]));
                        $indexes[] = [
                            'name' => $idxMatch[2],
                            'columns' => $columns,
                            'unique' => !empty($idxMatch[1])
                        ];
                    }
                    continue;
                }

                // Normal Column
                preg_match('/`(\w+)`\s+([a-zA-Z0-9(),]+)(.*)/', $col, $parts);
                if (count($parts) < 4) continue;

                $colName = $parts[1];
                $type = strtoupper($parts[2]);
                $extra = strtoupper($parts[3]);

                $line = "            \$table";

                // Map types
                if (strpos($type, 'BIGINT') !== false) {
                    if (strpos($extra, 'AUTO_INCREMENT') !== false) {
                        $line .= "->bigIncrements('$colName')";
                    } else {
                        $line .= "->unsignedBigInteger('$colName')";
                    }
                } elseif (strpos($type, 'INT') !== false) {
                    if (strpos($extra, 'AUTO_INCREMENT') !== false) {
                        $line .= "->increments('$colName')";
                    } else {
                        $line .= "->integer('$colName')";
                    }
                } elseif (strpos($type, 'VARCHAR') !== false) {
                    preg_match('/\((\d+)\)/', $type, $lenMatch);
                    $length = $lenMatch[1] ?? 255;
                    $line .= "->string('$colName', $length)";
                } elseif (strpos($type, 'TEXT') !== false) {
                    $line .= "->text('$colName')";
                } elseif (strpos($type, 'DECIMAL') !== false) {
                    preg_match('/\((\d+),(\d+)\)/', $type, $sizeMatch);
                    $precision = $sizeMatch[1] ?? 8;
                    $scale = $sizeMatch[2] ?? 2;
                    $line .= "->decimal('$colName', $precision, $scale)";
                } elseif (strpos($type, 'TIMESTAMP') !== false) {
                    $line .= "->timestamp('$colName')";
                } else {
                    $line .= "->string('$colName')"; // fallback
                }

                // Attributes
                if (strpos($extra, 'NOT NULL') === false) {
                    $line .= "->nullable()";
                }
                if (preg_match('/DEFAULT\s+([^\s]+)/', $extra, $defaultMatch)) {
                    $defaultVal = trim($defaultMatch[1], "'\"");
                    $line .= "->default('$defaultVal')";
                }

                $migration .= "\n" . $line . ";";
            }
            foreach ($indexes as $idx) {
                $columnsArray = $idx['columns'];
                $columnsString = count($columnsArray) === 1
                    ? "'{$columnsArray[0]}'"
                    : "['" . implode("', '", $columnsArray) . "']";

                if ($idx['unique']) {
                    $migration .= "\n            \$table->unique({$columnsString}, '{$idx['name']}');";
                } else {
                    $migration .= "\n            \$table->index({$columnsString}, '{$idx['name']}');";
                }
            }

            // Foreign keys
            foreach ($foreignKeys as $fk) {
                $colName = is_array($fk['columns']) ? $fk['columns'][0] : $fk['columns'];
                $refName = is_array($fk['references']) ? $fk['references'][0] : $fk['references'];

                $migration .= "\n            \$table->foreign('$colName'" . ($fk['name'] ? ", '{$fk['name']}'" : "") . ")";
                $migration .= "->references('$refName')->on('{$fk['on']}')";

                if ($fk['onDelete']) {
                    $migration .= "->onDelete('{$fk['onDelete']}')";
                }
                if ($fk['onUpdate']) {
                    $migration .= "->onUpdate('{$fk['onUpdate']}')";
                }
                $migration .= ";";
            }

            if ($withTimestamps) {
                $migration .= "\n            \$table->timestamps();";
            }

            $migration .= "\n        });\n    }\n\n    public function down()\n    {\n";
            $migration .= "        Schema::dropIfExists('$tableName');\n    }\n};\n";

            $timestamp = date('Y_m_d_His');
            $fileName = "{$timestamp}_create_{$tableName}_table.php";
            file_put_contents($outputDir . '/' . $fileName, $migration);

            echo "✅ Migration created: $fileName\n";
            sleep(1);
        }
    }
}