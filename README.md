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
docker compose up -d
```

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
E-mail: admin@phalcon.local
Senha: Admin@123
```

## Banco de dados

Dados do MySQL no Docker:

```text
Host interno: mysql
Porta interna: 3306
Porta no Windows: 3307
Banco: phalcon
Usuario: phalcon
Senha: phalcon123
Root: root
Senha root: root123
```

Acessar pelo terminal:

```powershell
docker compose exec mysql mysql -uphalcon -pphalcon123 phalcon
```

Acessar como root:

```powershell
docker compose exec mysql mysql -uroot -proot123 phalcon
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
- O phpMyAdmin usa a porta local `8081`.
- O MySQL esta exposto no Windows pela porta `3307`.
- O Redis esta exposto na porta `6379`.
- As rotas administrativas exigem login.
- Algumas rotas exigem perfil de administrador.

