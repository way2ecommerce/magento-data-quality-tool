<?php
/**
 * Herramienta de Calidad de Datos para Magento (Versión 4.0)
 *
 * Asistente de diagnóstico y reparación interactivo.
 *
 * MODOS DE USO:
 * ----------------------------------------------------------------------------------
 * 1. Análisis silencioso (por defecto): Muestra solo un resumen de los problemas.
 * >> php DataQualityTool.php
 *
 * 2. Ver progreso detallado: Muestra el análisis tabla por tabla.
 * >> php DataQualityTool.php --progress
 *
 * 3. Asistente de reparación: Busca problemas y ofrece solucionarlos interactivamente.
 * >> php DataQualityTool.php --fix
 * ----------------------------------------------------------------------------------
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ser ejecutado desde la línea de comandos (CLI).\n");
}

class DataQualityTool
{
    private ?\PDO $pdo = null;
    private string $dbName = '';
    private bool $isColorSupported;
    private array $issuesFound = [];

    public function __construct()
    {
        $this->isColorSupported = function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    /**
     * Inicia la herramienta.
     */
    public function run(array $args): void
    {
        $isFixMode = in_array('--fix', $args);
        $isProgressMode = in_array('--progress', $args);

        echo $this->colorize("Iniciando Herramienta de Calidad de Datos para Magento...\n", 'cyan');

        try {
            $this->connectToDatabase();
            $allTables = $this->getAllTables();

            if ($isProgressMode) {
                echo "Modo de progreso detallado activado.\n";
            }

            $this->performChecks($allTables, $isProgressMode);
            $this->printSummary();

            if ($isFixMode && !empty($this->issuesFound)) {
                $this->handleFixes();
            } elseif ($isFixMode) {
                echo $this->colorize("\nNo se encontraron problemas que reparar.\n", 'green');
            }

        } catch (\Exception $e) {
            echo "\n\n" . $this->colorize("ERROR: " . $e->getMessage() . "\n", 'light_red');
            exit(1);
        }
    }

    /**
     * Establece conexión con la base de datos usando la configuración de Magento.
     */
    private function connectToDatabase(): void
    {
        $envPath = './app/etc/env.php';
        if (!file_exists($envPath)) {
            throw new \Exception("No se pudo encontrar el fichero env.php. Asegúrate de ejecutar el script desde la raíz de Magento.");
        }
        
        $env = require $envPath;
        
        if (!isset($env['db']['connection']['default'])) {
            throw new \Exception("Configuración de base de datos no encontrada en env.php.");
        }
        
        $dbConfig = $env['db']['connection']['default'];
        $this->dbName = $dbConfig['dbname'];
        
        $dsn = "mysql:host={$dbConfig['host']};dbname={$this->dbName};charset=utf8";
        
        $this->pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Obtiene todas las tablas de la base de datos.
     */
    private function getAllTables(): array
    {
        $stmt = $this->pdo->query('SHOW TABLES');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Realiza las comprobaciones y recopila los problemas encontrados.
     */
    private function performChecks(array $tables, bool $progressMode): void
    {
        foreach ($tables as $index => $table) {
            if ($progressMode) {
                printf("[%d/%d] `%s`", $index + 1, count($tables), $table);
            }

            // Comprobar registros huérfanos
            $foreignKeys = $this->getForeignKeys($table);
            if (!empty($foreignKeys)) {
                $this->findOrphanedRecords($table, $foreignKeys);
            }

            if ($progressMode) {
                $status = !empty($this->issuesFound) && end($this->issuesFound)['table'] === $table ? "FALLO" : "OK";
                echo " -> " . $this->colorize("[$status]", $status === 'FALLO' ? 'light_red' : 'green') . "\n";
            }
        }
    }

    /**
     * Identifica registros huérfanos y los añade a la lista de problemas.
     */
    private function findOrphanedRecords(string $table, array $foreignKeys): void
    {
        foreach ($foreignKeys as $fk) {
            $sql = "SELECT 1 FROM `{$fk['TABLE_NAME']}` AS t1
                    LEFT JOIN `{$fk['REFERENCED_TABLE_NAME']}` AS t2
                    ON t1.`{$fk['COLUMN_NAME']}` = t2.`{$fk['REFERENCED_COLUMN_NAME']}`
                    WHERE t2.`{$fk['REFERENCED_COLUMN_NAME']}` IS NULL
                    AND t1.`{$fk['COLUMN_NAME']}` IS NOT NULL
                    LIMIT 1";
            
            $stmt = $this->pdo->query($sql);
            if ($stmt->fetch()) {
                $this->issuesFound[] = [
                    'type' => 'ORPHANED_RECORD',
                    'table' => $table,
                    'details' => "La columna `{$fk['COLUMN_NAME']}` en `{$fk['TABLE_NAME']}` apunta a registros inexistentes en `{$fk['REFERENCED_TABLE_NAME']}`.",
                    'fix_info' => [
                        'child_table' => $fk['TABLE_NAME'],
                        'child_column' => $fk['COLUMN_NAME'],
                        'parent_table' => $fk['REFERENCED_TABLE_NAME'],
                        'parent_column' => $fk['REFERENCED_COLUMN_NAME'],
                    ]
                ];
            }
        }
    }

    /**
     * Obtiene las claves foráneas de una tabla específica.
     */
    private function getForeignKeys(string $table): array
    {
        $sql = "SELECT * FROM information_schema.KEY_COLUMN_USAGE 
                WHERE REFERENCED_TABLE_SCHEMA = ? AND TABLE_NAME = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->dbName, $table]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Gestiona el proceso de reparación interactivo.
     */
    private function handleFixes(): void
    {
        echo "\n" . $this->colorize(str_repeat('-', 60), 'cyan');
        echo "\n" . $this->colorize(" ASISTENTE DE REPARACIÓN INTERACTIVO", 'cyan');
        echo "\n" . $this->colorize(str_repeat('-', 60), 'cyan') . "\n";
        
        echo "\n" . $this->colorize("¡ADVERTENCIA! Este proceso modificará tu base de datos.", 'yellow');
        echo "\n" . $this->colorize("Asegúrate de tener una copia de seguridad reciente antes de continuar.", 'yellow');
        echo "\n\n¿Estás seguro de que quieres continuar? [y/n]: ";
        
        $confirmation = strtolower(trim(fgets(STDIN)));
        if ($confirmation !== 'y') {
            echo "Reparación cancelada por el usuario.\n";
            return;
        }

        foreach ($this->issuesFound as $index => $issue) {
            echo "\n" . $this->colorize("---------------- Problema " . ($index + 1) . "/" . count($this->issuesFound) . " ----------------", 'cyan') . "\n";
            echo $this->colorize("Tipo:    ", 'green') . "Registros Huérfanos\n";
            echo $this->colorize("Tabla:   ", 'green') . "`{$issue['table']}`\n";
            echo $this->colorize("Detalle: ", 'green') . "{$issue['details']}\n";

            if ($issue['type'] === 'ORPHANED_RECORD') {
                $this->fixOrphanedRecord($issue['fix_info']);
            }
        }

        echo "\n" . $this->colorize("Proceso de reparación finalizado.", 'green') . "\n";
        echo "Se recomienda limpiar la caché de Magento: `bin/magento cache:flush`\n";
    }

    /**
     * Repara registros huérfanos de forma interactiva.
     */
    private function fixOrphanedRecord(array $fixInfo): void
    {
        $fi = $fixInfo; // alias más corto
        $countSql = "SELECT COUNT(*) FROM `{$fi['child_table']}` AS t1
                     LEFT JOIN `{$fi['parent_table']}` AS t2 ON t1.`{$fi['child_column']}` = t2.`{$fi['parent_column']}`
                     WHERE t2.`{$fi['parent_column']}` IS NULL AND t1.`{$fi['child_column']}` IS NOT NULL";
        
        $count = $this->pdo->query($countSql)->fetchColumn();

        if ($count == 0) {
            echo $this->colorize("Parece que este problema ya ha sido solucionado. No se encontraron registros huérfanos.\n", 'yellow');
            return;
        }

        echo $this->colorize("Impacto: ", 'green') . "Se han encontrado {$count} registros huérfanos para eliminar.\n";
        echo $this->colorize("Acción:  ", 'green') . "DELETE FROM `{$fi['child_table']}` WHERE la referencia no exista.\n\n";

        echo "¿Proceder con la eliminación? [y/n]: ";
        $choice = strtolower(trim(fgets(STDIN)));

        if ($choice === 'y') {
            $deleteSql = "DELETE t1 FROM `{$fi['child_table']}` AS t1
                          LEFT JOIN `{$fi['parent_table']}` AS t2 ON t1.`{$fi['child_column']}` = t2.`{$fi['parent_column']}`
                          WHERE t2.`{$fi['parent_column']}` IS NULL AND t1.`{$fi['child_column']}` IS NOT NULL";
            
            try {
                $affectedRows = $this->pdo->exec($deleteSql);
                echo $this->colorize("ÉXITO: Se han eliminado {$affectedRows} registros huérfanos de `{$fi['child_table']}`.\n", 'green');
            } catch (\PDOException $e) {
                echo $this->colorize("ERROR AL REPARAR: " . $e->getMessage() . "\n", 'light_red');
            }
        } else {
            echo "Acción omitida por el usuario.\n";
        }
    }

    /**
     * Muestra el resumen final del análisis.
     */
    private function printSummary(): void
    {
        $issueCount = count($this->issuesFound);
        echo "\n" . $this->colorize("----------------- RESUMEN DEL ANÁLISIS -----------------", 'cyan') . "\n";
        
        if ($issueCount > 0) {
            echo "Se encontraron " . $this->colorize($issueCount . " problemas de integridad de datos:", 'light_red') . "\n\n";
            foreach($this->issuesFound as $issue) {
                echo " - " . $this->colorize("[FALLO]", "light_red") . " en tabla `{$issue['table']}`: {$issue['details']}\n";
            }
            
            if (!$this->isColorSupported) {
                echo "\n";
            }
            
            echo "\nPara solucionar estos problemas, ejecuta el script con la opción: " . $this->colorize('php DataQualityTool.php --fix', 'yellow') . "\n";
        } else {
            echo $this->colorize("✅ ¡No se encontraron problemas de integridad de datos!", 'green') . "\n";
        }
        
        echo $this->colorize("----------------------------------------------------------\n", 'cyan');
    }

    /**
     * Aplica colores al texto en la consola (si es compatible).
     */
    private function colorize(string $text, string $color): string
    {
        if (!$this->isColorSupported) {
            return $text;
        }
        
        $colors = [
            'green' => '0;32',
            'light_red' => '1;31',
            'red' => '0;31',
            'yellow' => '1;33',
            'cyan' => '0;36'
        ];
        
        return "\033[" . $colors[$color] . "m" . $text . "\033[0m";
    }
}

// Ejecutar la herramienta
$tool = new DataQualityTool();
$tool->run($argv);