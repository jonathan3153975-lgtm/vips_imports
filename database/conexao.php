<?php


Class Connection {
	private $conn = null;
    private $env = null;

    private function loadEnv(){
        if ($this->env !== null) {
            return;
        }

        $candidatos = [
            dirname(__DIR__) . '/.env', // raiz do projeto
            __DIR__ . '/.env',          // pasta database (fallback)
        ];

        $envPath = null;
        foreach ($candidatos as $caminho) {
            if (file_exists($caminho)) {
                $envPath = $caminho;
                break;
            }
        }

        if ($envPath === null) {
            throw new Exception('.env não encontrado. Procurado em: ' . implode(' ou ', $candidatos));
        }


        $env = parse_ini_file($envPath);
        if ($env === false) {
            throw new Exception('Falha ao ler o .env');
        }


        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($required as $key) {
            if (!isset($env[$key]) || $env[$key] === '') {
                throw new Exception("Variável {$key} ausente no .env");
            }
        }

        $this->env = $env;
    }

    public function PDOconn(){

        if ($this->conn !== null) {
            return $this->conn;
        }


        try {
        
            $this->loadEnv();
            $dsn = "mysql:host={$this->env['DB_HOST']};dbname={$this->env['DB_NAME']};charset=utf8";
            $this->conn = new PDO(
                $dsn,
                $this->env['DB_USER'],
                $this->env['DB_PASS'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            return $this->conn;



        } catch (Throwable $e) {
            // IMPORTANTE: durante desenvolvimento
            throw new Exception(
                'Erro ao conectar no banco: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

	public function query($SQL) {
		try{
			$this->PDOconn();
			
            // Preparar e executar a consulta SQL para selecionar todos os registros da tabela 'usuarios'
			$stmt = $this->conn->prepare($SQL);
			$stmt->execute();
	        
            // Buscar todos os registros como um array associativo
	        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
	        
            // Verificar se há registros e exibi-los
	        $linhas = [];
	        
            if (count($registros) > 0) {
				foreach ($registros as $registro) {
					$linhas[] = $registro;
				}
			}
            
            else {
				$linhas = null;
			}


			$stmt = null;
	        return $linhas;


	    }catch (PDOException $e) {
			
            return false;  // ← distincto de null (sem dados) e [] (com dados)
		}
	}

	public function update($tabela, $dados, $condicao) {
	    if (!is_null($condicao) && !empty($dados)) {
	        try {
	            // Estabelecer conexão
	            $this->PDOconn();

	            // Criar a parte SET da instrução SQL
	            $colunas = [];
	            foreach ($dados as $key => $value) {
					$colunas[] = "{$key} = :{$key}";
				}
	            $colunas = implode(', ', $colunas);

	            // Criar a parte WHERE da instrução SQL
	            $where = [];
	            foreach ($condicao as $key => $value) {
					$where[] = "{$key} = :cond_{$key}";
				}
	            $where = implode(' AND ', $where);

	            // Preparar a instrução SQL
	            $SQL = "UPDATE {$tabela} SET {$colunas} WHERE {$where}";
	            $stmt = $this->conn->prepare($SQL);

	            // Vincular os parâmetros da parte SET
	            foreach ($dados as $coluna => $valor) {
					$stmt->bindValue(":$coluna", $valor, $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
				}

	            // Vincular os parâmetros da parte WHERE
	            foreach ($condicao as $key => $value) {
					$stmt->bindValue(":cond_{$key}", $value);
				}

	            // Executar a declaração
	            $stmt->execute();

	            // Opcional: Verificar se a atualização foi bem-sucedida
	            $affectedRows = $stmt->rowCount();
	            return $affectedRows;

	        } catch (PDOException $e) {
	            return false;
	        }
	    } else {
	        return false;
	    }
	}

	public function insert($tabela, $dados){
		$this->PDOconn();
	    try {
	        // Verificar se $dados não está vazio
	        if (empty($dados)) {
				throw new InvalidArgumentException('Nenhum dado fornecido para inserção.');
			}

	        // Construir a parte da instrução SQL para a inserção
	        $colunas = array_keys($dados);
	        $valores = array_map(function($coluna) {
	            return ":{$coluna}";
	        }, $colunas);

	        $colunasString = implode(', ', $colunas);
	        $valoresString = implode(', ', $valores);

	        $SQL = "INSERT INTO {$tabela} ({$colunasString}) VALUES ({$valoresString})";

	        // Preparar a instrução SQL
	        $stmt = $this->conn->prepare($SQL);

	        // Vincular os parâmetros
	        foreach ($dados as $coluna => $valor) {
				$stmt->bindValue(":{$coluna}", $valor, $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
			}

	        #return $SQL;
	        // Executar a declaração
	        $stmt->execute();
	        // Retornar o ID do registro inserido
	        return $this->conn->lastInsertId();
	    }
		catch (PDOException $e) {
			return false;

		} catch (InvalidArgumentException $e) {
			return false;
		}		
	}

	public function delete($tabela, $condicao) {
		// Verifica se a tabela e a condição são válidas
		if (!is_null($condicao) && !empty($condicao)) {
			try {
				// Estabelecer conexão
				$this->PDOconn();

				// Construir a cláusula WHERE para a consulta SQL
				$where = array_map(function($key) {
					return "{$key} = :cond_{$key}";
				}, array_keys($condicao));

				$whereClause = implode(' AND ', $where);

				// Preparar a instrução SQL DELETE
				$sql = "DELETE FROM {$tabela} WHERE {$whereClause}";
				$stmt = $this->conn->prepare($sql);

				// Vincular os parâmetros da cláusula WHERE
				foreach ($condicao as $key => $value) {
					$stmt->bindValue(":cond_{$key}", $value);
				}

				// Executar a instrução SQL
				$stmt->execute();

				// Retorna o número de linhas afetadas
				return $stmt->rowCount();

			} catch (PDOException $e) {
				return false;
			}
		} else {
			return false;
		}
	}
}
?>
