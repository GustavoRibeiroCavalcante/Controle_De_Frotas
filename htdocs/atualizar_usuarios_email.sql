-- Execute UMA VEZ no phpMyAdmin, selecionando o banco existente.
-- Não cria outro banco nem altera registros de Funcionario.
-- Usuários atuais precisarão confirmar seu e-mail no próximo login.
ALTER TABLE Usuarios
    ADD COLUMN Email_Verificado_Em DATETIME NULL DEFAULT NULL,
    ADD COLUMN Email_Verificacao_Hash CHAR(64) NULL DEFAULT NULL,
    ADD COLUMN Email_Verificacao_Expira DATETIME NULL DEFAULT NULL,
    ADD COLUMN Email_Verificacao_Enviado_Em DATETIME NULL DEFAULT NULL,
    ADD COLUMN Reset_Senha_Hash CHAR(64) NULL DEFAULT NULL,
    ADD COLUMN Reset_Senha_Expira DATETIME NULL DEFAULT NULL,
    ADD COLUMN Reset_Senha_Enviado_Em DATETIME NULL DEFAULT NULL;
