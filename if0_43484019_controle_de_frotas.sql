-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql303.infinityfree.com
-- Tempo de geração: 25/11/2026 às 11:18
-- Versão do servidor: 11.4.14-MariaDB
-- Versão do PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `if0_43484019_controle_de_frotas`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `Abastecimentos`
--

CREATE TABLE `Abastecimentos` (
  `Id_Abastecimento` int(11) NOT NULL,
  `Id_Veiculo` int(11) NOT NULL,
  `Data_Abastecimento` datetime NOT NULL,
  `Litros` decimal(10,2) NOT NULL,
  `Valor_Total` decimal(10,2) NOT NULL,
  `Posto` varchar(100) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `Abastecimentos`
--

INSERT INTO `Abastecimentos` (`Id_Abastecimento`, `Id_Veiculo`, `Data_Abastecimento`, `Litros`, `Valor_Total`, `Posto`) VALUES
(1, 1, '0000-00-00 00:00:00', '124.00', '21412.00', 'Ipiranga');

-- --------------------------------------------------------

--
-- Estrutura para tabela `Banco_de_horas`
--

CREATE TABLE `Banco_de_horas` (
  `Id_Banco` int(11) NOT NULL,
  `Id_Funcionario` int(11) NOT NULL,
  `Horas` decimal(5,2) NOT NULL,
  `Data_Registro` date NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `Banco_de_horas`
--

INSERT INTO `Banco_de_horas` (`Id_Banco`, `Id_Funcionario`, `Horas`, `Data_Registro`) VALUES
(1, 2, '12.00', '1333-03-12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `Carga`
--

CREATE TABLE `Carga` (
  `Id_Carga` int(11) NOT NULL,
  `Descricao` varchar(255) NOT NULL,
  `Peso` decimal(10,2) DEFAULT NULL,
  `Origem` varchar(100) DEFAULT NULL,
  `Destino` varchar(100) DEFAULT NULL,
  `Data_Envio` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `Carga`
--

INSERT INTO `Carga` (`Id_Carga`, `Descricao`, `Peso`, `Origem`, `Destino`, `Data_Envio`) VALUES
(1, 'Produto sensivel', '12.00', 'São paulo', 'Amazonas', '2000-12-12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `Funcionario`
--

CREATE TABLE `Funcionario` (
  `Id_Funcionario` int(11) NOT NULL,
  `CPF` char(11) NOT NULL,
  `Nome` varchar(100) NOT NULL,
  `Data_Nascimento` date NOT NULL,
  `Funcao` varchar(50) NOT NULL,
  `Telefone` varchar(15) NOT NULL,
  `Salario` decimal(10,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `Funcionario`
--

INSERT INTO `Funcionario` (`Id_Funcionario`, `CPF`, `Nome`, `Data_Nascimento`, `Funcao`, `Telefone`, `Salario`) VALUES
(2, '11111111111', 'GustavoRibeiro', '1998-03-12', 'Administrador', '123123123123', '123123.00'),
(3, '11122222222', 'Iraneide', '2000-12-12', 'Gerente', '1123123', '99999999.99'),
(4, '4141414111', 'TestFAcil', '2000-12-12', 'Usuario', '55142299', '12312313.00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `Manutencoes`
--

CREATE TABLE `Manutencoes` (
  `Id_Manutencao` int(11) NOT NULL,
  `Id_Veiculo` int(11) NOT NULL,
  `Tipo` varchar(100) DEFAULT NULL,
  `Descricao` text DEFAULT NULL,
  `Data_Manutencao` date NOT NULL,
  `Custo` decimal(10,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `Multas`
--

CREATE TABLE `Multas` (
  `Id_Multa` int(11) NOT NULL,
  `Id_Veiculo` int(11) NOT NULL,
  `Id_Funcionario` int(11) NOT NULL,
  `Data_Multa` date NOT NULL,
  `Valor` decimal(10,2) NOT NULL,
  `Motivo` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `Usuarios`
--

CREATE TABLE `Usuarios` (
  `Id` int(11) NOT NULL,
  `Id_Funcionario` int(11) NOT NULL,
  `Senha` varchar(255) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Nivel_Permissao` enum('ADMIN','GERENTE','USUARIO','Motorista','RH') NOT NULL,
  `Email_Verificado_Em` datetime DEFAULT NULL,
  `Email_Verificacao_Hash` char(64) DEFAULT NULL,
  `Email_Verificacao_Expira` datetime DEFAULT NULL,
  `Email_Verificacao_Enviado_Em` datetime DEFAULT NULL,
  `Reset_Senha_Hash` char(64) DEFAULT NULL,
  `Reset_Senha_Expira` datetime DEFAULT NULL,
  `Reset_Senha_Enviado_Em` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `Usuarios`
--

INSERT INTO `Usuarios` (`Id`, `Id_Funcionario`, `Senha`, `Email`, `Nivel_Permissao`, `Email_Verificado_Em`, `Email_Verificacao_Hash`, `Email_Verificacao_Expira`, `Email_Verificacao_Enviado_Em`, `Reset_Senha_Hash`, `Reset_Senha_Expira`, `Reset_Senha_Enviado_Em`) VALUES
(2, 2, '$2y$12$B5gmOyQd83oMWOSvC02YY.NNzYkYyN7KyeKAg9aTt4qB3VUN34e8u', 'Guansa123l@gmail.com', 'ADMIN', '2026-11-21 19:38:38', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, '$2y$12$H48sEofKTJ9BNErzgJQ5reF2it66opycOwdZ0TnlTZdF8Uf6nfySS', 'Iraneide@Iraneide.com', 'USUARIO', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 4, '$2y$12$05fCoY/m2IxvFZowlbz95ODMiujZcGTete1IE/gbOBU73RueMqm0K', 'fanawaw167@dsmspaces.com', 'USUARIO', '2026-11-23 11:30:57', NULL, NULL, NULL, '6cc4ced1a7fc683f35359902b09e341de1a398599f87765e1b1ab8fa09b6956e', '2026-11-23 12:04:46', '2026-11-23 11:34:46');

-- --------------------------------------------------------

--
-- Estrutura para tabela `Veiculos`
--

CREATE TABLE `Veiculos` (
  `Id_Veiculo` int(11) NOT NULL,
  `Placa` varchar(10) NOT NULL,
  `Modelo` varchar(100) NOT NULL,
  `Marca` varchar(100) DEFAULT NULL,
  `Ano` int(11) DEFAULT NULL,
  `Capacidade_Carga` decimal(10,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `Veiculos`
--

INSERT INTO `Veiculos` (`Id_Veiculo`, `Placa`, `Modelo`, `Marca`, `Ano`, `Capacidade_Carga`) VALUES
(1, '1313asdasd', 'asdadda', 'asdasda', 2012, '12.00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `Viagens`
--

CREATE TABLE `Viagens` (
  `Id_Viagem` int(11) NOT NULL,
  `Id_Veiculo` int(11) NOT NULL,
  `Id_Funcionario` int(11) NOT NULL,
  `Id_Carga` int(11) DEFAULT NULL,
  `Data_Saida` datetime DEFAULT NULL,
  `Data_Chegada` datetime DEFAULT NULL,
  `Origem` varchar(100) NOT NULL,
  `Destino` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `Viagens`
--

INSERT INTO `Viagens` (`Id_Viagem`, `Id_Veiculo`, `Id_Funcionario`, `Id_Carga`, `Data_Saida`, `Data_Chegada`, `Origem`, `Destino`) VALUES
(1, 1, 4, 1, '2000-12-12 21:21:00', NULL, 'São paulo', 'Amazonas'),
(2, 1, 3, 1, '4114-12-12 12:14:00', '0000-00-00 00:00:00', '1212', '124124'),
(3, 1, 3, 1, '4114-12-12 12:14:00', '0000-00-00 00:00:00', '1212', '124124'),
(4, 1, 3, 1, '0211-03-12 12:13:00', '0000-00-00 00:00:00', '12313', '13123123');

--
-- Índices de tabelas apagadas
--

--
-- Índices de tabela `Abastecimentos`
--
ALTER TABLE `Abastecimentos`
  ADD PRIMARY KEY (`Id_Abastecimento`),
  ADD KEY `idx_abastecimentos_veiculo` (`Id_Veiculo`),
  ADD KEY `idx_abastecimentos_data` (`Data_Abastecimento`);

--
-- Índices de tabela `Banco_de_horas`
--
ALTER TABLE `Banco_de_horas`
  ADD PRIMARY KEY (`Id_Banco`),
  ADD KEY `idx_banco_funcionario_data` (`Id_Funcionario`,`Data_Registro`);

--
-- Índices de tabela `Carga`
--
ALTER TABLE `Carga`
  ADD PRIMARY KEY (`Id_Carga`),
  ADD KEY `idx_carga_origem_destino` (`Origem`,`Destino`),
  ADD KEY `idx_carga_data_envio` (`Data_Envio`);

--
-- Índices de tabela `Funcionario`
--
ALTER TABLE `Funcionario`
  ADD PRIMARY KEY (`Id_Funcionario`),
  ADD UNIQUE KEY `CPF` (`CPF`),
  ADD KEY `idx_funcionario_nome` (`Nome`),
  ADD KEY `idx_funcionario_funcao` (`Funcao`);

--
-- Índices de tabela `Manutencoes`
--
ALTER TABLE `Manutencoes`
  ADD PRIMARY KEY (`Id_Manutencao`),
  ADD KEY `idx_manutencoes_veiculo` (`Id_Veiculo`),
  ADD KEY `idx_manutencoes_data` (`Data_Manutencao`);

--
-- Índices de tabela `Multas`
--
ALTER TABLE `Multas`
  ADD PRIMARY KEY (`Id_Multa`),
  ADD KEY `idx_multas_veiculo` (`Id_Veiculo`),
  ADD KEY `idx_multas_funcionario` (`Id_Funcionario`),
  ADD KEY `idx_multas_data` (`Data_Multa`);

--
-- Índices de tabela `Usuarios`
--
ALTER TABLE `Usuarios`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `idx_usuarios_funcionario` (`Id_Funcionario`),
  ADD KEY `idx_usuarios_email` (`Email`);

--
-- Índices de tabela `Veiculos`
--
ALTER TABLE `Veiculos`
  ADD PRIMARY KEY (`Id_Veiculo`),
  ADD UNIQUE KEY `Placa` (`Placa`);

--
-- Índices de tabela `Viagens`
--
ALTER TABLE `Viagens`
  ADD PRIMARY KEY (`Id_Viagem`),
  ADD KEY `idx_viagens_veiculo` (`Id_Veiculo`),
  ADD KEY `idx_viagens_funcionario` (`Id_Funcionario`),
  ADD KEY `idx_viagens_carga` (`Id_Carga`),
  ADD KEY `idx_viagens_data_saida` (`Data_Saida`),
  ADD KEY `idx_viagens_origem_destino` (`Origem`,`Destino`);

--
-- AUTO_INCREMENT de tabelas apagadas
--

--
-- AUTO_INCREMENT de tabela `Abastecimentos`
--
ALTER TABLE `Abastecimentos`
  MODIFY `Id_Abastecimento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `Banco_de_horas`
--
ALTER TABLE `Banco_de_horas`
  MODIFY `Id_Banco` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `Carga`
--
ALTER TABLE `Carga`
  MODIFY `Id_Carga` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `Funcionario`
--
ALTER TABLE `Funcionario`
  MODIFY `Id_Funcionario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `Manutencoes`
--
ALTER TABLE `Manutencoes`
  MODIFY `Id_Manutencao` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `Multas`
--
ALTER TABLE `Multas`
  MODIFY `Id_Multa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `Usuarios`
--
ALTER TABLE `Usuarios`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `Veiculos`
--
ALTER TABLE `Veiculos`
  MODIFY `Id_Veiculo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `Viagens`
--
ALTER TABLE `Viagens`
  MODIFY `Id_Viagem` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
