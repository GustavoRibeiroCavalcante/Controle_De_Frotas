CREATE DATABASE IF NOT EXISTS control;
USE control;

CREATE TABLE IF NOT EXISTS Funcionario (
    Id_Funcionario INT AUTO_INCREMENT PRIMARY KEY,
    CPF CHAR(11) NOT NULL UNIQUE,
    Nome VARCHAR(100) NOT NULL,
    Data_Nascimento DATE NOT NULL,
    Funcao VARCHAR(50) NOT NULL,
    Telefone VARCHAR(15) NOT NULL,
    Salario DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS Usuarios (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Id_Funcionario INT NOT NULL,
    Senha VARCHAR(255) NOT NULL,
    Email VARCHAR(100) NOT NULL,
    Nivel_Permissao ENUM(
        'ADMIN',
        'GERENTE',
        'USUARIO',
        'Motorista',
        'RH'
    ) NOT NULL,
    FOREIGN KEY (Id_Funcionario)
        REFERENCES Funcionario(Id_Funcionario)
);

CREATE TABLE IF NOT EXISTS Carga (
    Id_Carga INT AUTO_INCREMENT PRIMARY KEY,
    Descricao VARCHAR(255) NOT NULL,
    Peso DECIMAL(10,2),
    Origem VARCHAR(100),
    Destino VARCHAR(100),
    Data_Envio DATE
);

CREATE TABLE IF NOT EXISTS Veiculos (
    Id_Veiculo INT AUTO_INCREMENT PRIMARY KEY,
    Placa VARCHAR(10) UNIQUE NOT NULL,
    Modelo VARCHAR(100) NOT NULL,
    Marca VARCHAR(100),
    Ano INT,
    Capacidade_Carga DECIMAL(10,2)
);

CREATE TABLE IF NOT EXISTS Banco_de_horas (
    Id_Banco INT AUTO_INCREMENT PRIMARY KEY,
    Id_Funcionario INT NOT NULL,
    Horas DECIMAL(5,2) NOT NULL,
    Data_Registro DATE NOT NULL,
    FOREIGN KEY (Id_Funcionario)
        REFERENCES Funcionario(Id_Funcionario)
);

CREATE TABLE IF NOT EXISTS Abastecimentos (
    Id_Abastecimento INT AUTO_INCREMENT PRIMARY KEY,
    Id_Veiculo INT NOT NULL,
    Data_Abastecimento DATETIME NOT NULL,
    Litros DECIMAL(10,2) NOT NULL,
    Valor_Total DECIMAL(10,2) NOT NULL,
    Posto VARCHAR(100),
    FOREIGN KEY (Id_Veiculo)
        REFERENCES Veiculos(Id_Veiculo)
);

CREATE TABLE IF NOT EXISTS Manutencoes (
    Id_Manutencao INT AUTO_INCREMENT PRIMARY KEY,
    Id_Veiculo INT NOT NULL,
    Tipo VARCHAR(100),
    Descricao TEXT,
    Data_Manutencao DATE NOT NULL,
    Custo DECIMAL(10,2),
    FOREIGN KEY (Id_Veiculo)
        REFERENCES Veiculos(Id_Veiculo)
);

CREATE TABLE IF NOT EXISTS Viagens (
    Id_Viagem INT AUTO_INCREMENT PRIMARY KEY,
    Id_Veiculo INT NOT NULL,
    Id_Funcionario INT NOT NULL,
    Id_Carga INT,
    Data_Saida DATETIME,
    Data_Chegada DATETIME,
    Origem VARCHAR(100) NOT NULL,
    Destino VARCHAR(100) NOT NULL,
    FOREIGN KEY (Id_Veiculo)
        REFERENCES Veiculos(Id_Veiculo),
    FOREIGN KEY (Id_Funcionario)
        REFERENCES Funcionario(Id_Funcionario),
    FOREIGN KEY (Id_Carga)
        REFERENCES Carga(Id_Carga)
);

CREATE TABLE IF NOT EXISTS Multas (
    Id_Multa INT AUTO_INCREMENT PRIMARY KEY,
    Id_Veiculo INT NOT NULL,
    Id_Funcionario INT NOT NULL,
    Data_Multa DATE NOT NULL,
    Valor DECIMAL(10,2) NOT NULL,
    Motivo VARCHAR(255),
    FOREIGN KEY (Id_Veiculo)
        REFERENCES Veiculos(Id_Veiculo),
    FOREIGN KEY (Id_Funcionario)
        REFERENCES Funcionario(Id_Funcionario)
);


CREATE INDEX idx_funcionario_nome
ON Funcionario (Nome);

CREATE INDEX idx_funcionario_funcao
ON Funcionario (Funcao);


CREATE INDEX idx_usuarios_funcionario
ON Usuarios (Id_Funcionario);

CREATE INDEX idx_usuarios_email
ON Usuarios (Email);


CREATE INDEX idx_carga_origem_destino
ON Carga (Origem, Destino);

CREATE INDEX idx_carga_data_envio
ON Carga (Data_Envio);


CREATE INDEX idx_banco_funcionario_data
ON Banco_de_horas (Id_Funcionario, Data_Registro);


CREATE INDEX idx_abastecimentos_veiculo
ON Abastecimentos (Id_Veiculo);

CREATE INDEX idx_abastecimentos_data
ON Abastecimentos (Data_Abastecimento);


CREATE INDEX idx_manutencoes_veiculo
ON Manutencoes (Id_Veiculo);

CREATE INDEX idx_manutencoes_data
ON Manutencoes (Data_Manutencao);


CREATE INDEX idx_viagens_veiculo
ON Viagens (Id_Veiculo);

CREATE INDEX idx_viagens_funcionario
ON Viagens (Id_Funcionario);

CREATE INDEX idx_viagens_carga
ON Viagens (Id_Carga);

CREATE INDEX idx_viagens_data_saida
ON Viagens (Data_Saida);

CREATE INDEX idx_viagens_origem_destino
ON Viagens (Origem, Destino);


CREATE INDEX idx_multas_veiculo
ON Multas (Id_Veiculo);

CREATE INDEX idx_multas_funcionario
ON Multas (Id_Funcionario);

CREATE INDEX idx_multas_data
ON Multas (Data_Multa);
