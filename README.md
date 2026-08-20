# Projeto Phalcon

Aplicacao de estudos em **PHP 8.2 + Phalcon 5**, usando Docker, MySQL, Redis, Vue.js, jQuery legado e modulo Gantt.

## Requisitos

- Docker Desktop
- Docker Compose
- Git
- Navegador atualizado

## Subir o ambiente

Abra o PowerShell:

```powershell
cd C:\wamp64\www\phalcon
copy .env.example .env
notepad .env
docker compose up -d
```

Antes de subir o ambiente, altere no `.env` as senhas de `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD` e `ADMIN_PASSWORD`.
Se quiser um superadministrador separado, defina tambem `MASTER_EMAIL`,
`MASTER_PASSWORD` e `MASTER_NAME`. Se `MASTER_EMAIL` ficar vazio, o usuario de
`ADMIN_EMAIL` sera promovido para `master` pelas migrations.

Confira se os containers estao rodando:

```powershell
docker compose ps
```

## Instalar dependencias PHP

```powershell
cd C:\wamp64\www\phalcon
docker compose exec app composer install
```

## Rodar migrations

```powershell
docker compose exec app composer migrate
```

## Rodar seeders

Se precisar popular dados iniciais:

```powershell
docker compose exec app composer seed
```

Tambem existe o seed de empresa ficticia:

```powershell
docker compose exec app php bin/seed_fictitious_company.php
```

## URLs locais

Aplicacao:

```text
http://localhost:8080
```

Login:

```text
http://localhost:8080/login
```

Dashboard:

```text
http://localhost:8080/dashboard
```

Gestao de usuarios:

```text
http://localhost:8080/users
```

Projetos:

```text
http://localhost:8080/projects
```

Cadastro de projetos:

```text
http://localhost:8080/projects/create
```

Gantt geral:

```text
http://localhost:8080/gantt
```

Gantt por projeto:

```text
http://localhost:8080/projects/{id}/gantt
```

Perfil da empresa:

```text
http://localhost:8080/companies/profile
```

phpMyAdmin:

```text
http://localhost:8081
```

## Acesso inicial

```text
E-mail: valor de ADMIN_EMAIL no .env
Senha: valor de ADMIN_PASSWORD no .env
```

Se `MASTER_EMAIL` e `MASTER_PASSWORD` estiverem definidos, use esses valores
para acessar com permissao total (`master`).

## Banco de dados

Dados do MySQL no Docker:

```text
Host interno: mysql
Porta interna: 3306
Porta no Windows: 3307
Banco: phalcon
Usuario: phalcon
Senha: valor de DB_PASSWORD no .env
Root: root
Senha root: valor de MYSQL_ROOT_PASSWORD no .env
```

Acessar pelo terminal:

```powershell
docker compose exec mysql sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

Acessar como root:

```powershell
docker compose exec mysql sh -lc 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

## Redis

O Redis esta disponivel no Docker:

```text
Host interno: redis
Porta: 6379
Container: phalcon_redis
```

Entrar no Redis CLI:

```powershell
docker compose exec redis redis-cli
```

Testar conexao:

```text
PING
```

Resposta esperada:

```text
PONG
```

## Containers

Containers principais:

```text
phalcon_app
phalcon_mysql
phalcon_redis
phalcon_phpmyadmin
```

Ver logs da aplicacao:

```powershell
docker compose logs -f app
```

Ver logs do MySQL:

```powershell
docker compose logs -f mysql
```

Ver logs do Redis:

```powershell
docker compose logs -f redis
```

## Comandos uteis

Validar sintaxe PHP do projeto:

```powershell
docker compose exec app composer lint
```

Executar smoke test HTTP local:

```powershell
docker compose exec app composer smoke
```

Validar variaveis obrigatorias antes de homologacao/producao:

```powershell
docker compose -f compose.prod.yaml exec app composer production:check
```

Parar o ambiente:

```powershell
docker compose down
```

Subir novamente:

```powershell
docker compose up -d
```

Recriar imagem da aplicacao:

```powershell
docker compose build app
docker compose up -d
```

Entrar no container PHP:

```powershell
docker compose exec app bash
```

Validar sintaxe PHP de um arquivo:

```powershell
docker compose exec app php -l app/config/routes.php
```

Atualizar autoload do Composer:

```powershell
docker compose exec app composer dump-autoload
```

## Observacoes

- O projeto usa Apache na porta local `8080`.
- Endpoints de operacao: `/healthz` para liveness e `/readyz` para readiness de banco/Redis.
- O phpMyAdmin usa a porta local `8081`.
- O MySQL esta exposto no Windows pela porta `3307`.
- O Redis esta exposto na porta `6379`.
- As rotas administrativas exigem login.
- Algumas rotas exigem perfil de administrador.
