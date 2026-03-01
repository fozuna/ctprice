# Instruções para Configuração na Hostinger (Hospedagem Compartilhada)

O seu projeto PHP utiliza **Banco de Dados MySQL** e **Variáveis de Ambiente (.env)**. Como você subiu via Git e o arquivo `.env` é ignorado por segurança, a aplicação não consegue conectar ao banco, resultando na exibição de dados de exemplo ("João Silva", etc) e perda de configurações dinâmicas.

Siga estes passos para corrigir:

## 1. Criar o Banco de Dados
1. Acesse o painel da Hostinger (hpanel).
2. Vá em **Bancos de Dados** > **Gerenciamento de Banco de Dados MySQL**.
3. Crie um novo banco de dados:
   - **Nome do Banco de Dados:** (ex: `u123456789_ctprice`)
   - **Nome de Usuário:** (ex: `u123456789_admin`)
   - **Senha:** (crie uma senha forte e ANOTE)
4. Clique em **Criar**.

## 2. Importar a Estrutura (Tabelas)
1. No mesmo painel de Banco de Dados, clique em **Entrar no phpMyAdmin** ao lado do banco criado.
2. No phpMyAdmin, selecione o banco de dados na esquerda.
3. Vá na aba **Importar**.
4. Clique em **Escolher arquivo** e selecione o arquivo `database.sql` que está na pasta do seu projeto (você pode baixá-lo do seu repositório Git ou do seu computador local).
5. Clique em **Executar** (botão no final da página).
   - *Isso criará as tabelas `testimonials`, `partners`, `services`, `news`.*

## 3. Configurar o Arquivo .env
O arquivo `.env` contém as senhas. Ele não existe na hospedagem.
1. No painel da Hostinger, vá em **Arquivos** > **Gerenciador de Arquivos**.
2. Navegue até a pasta raiz do seu projeto (onde estão as pastas `app`, `public_html`, etc).
3. Procure pelo arquivo `.env.example`.
4. Clique com o botão direito e selecione **Renomear**. Mude para `.env` (apenas `.env`).
5. Clique com o botão direito no `.env` e selecione **Editar**.
6. Preencha com os dados do banco que você criou no Passo 1:

```ini
DB_HOST=localhost
DB_NAME=u123456789_ctprice  (seu nome de banco COMPLETO)
DB_USER=u123456789_admin    (seu usuário COMPLETO)
DB_PASS=SuaSenhaForteAqui
APP_URL=https://seudominio.com.br
```

7. Salve o arquivo.

## 4. Verificar Permissões e Caminhos
Se as imagens dos parceiros não carregarem:
1. Certifique-se de que a pasta `public_html/assets` tem permissões de leitura (755).
2. Verifique se o arquivo `public_html/index.php` está sendo executado corretamente.

## 5. Testar
Acesse `https://seudominio.com.br/check_setup.php` para ver se está tudo verde.
- Se conectar com sucesso, apague o arquivo `check_setup.php` pelo Gerenciador de Arquivos.

---
**Nota sobre o Git:** Sempre que você fizer alterações no banco localmente (novas tabelas), precisará exportar e importar na Hostinger ou rodar os comandos SQL manualmente lá.
