# Deploy na VM do Google Cloud (Cloud Build + Artifact Registry + HTTPS)

A imagem Docker e compilada no **Cloud Build** (servidores do Google) e publicada
no **Artifact Registry**. A VM apenas **baixa a imagem pronta** e roda — ela nunca
compila o Phalcon, entao nao trava por falta de CPU/RAM.

Fluxo:

```text
Cloud Build (compila) --> Artifact Registry (guarda a imagem) --> VM (docker pull + up)
                                                                    + Caddy (HTTPS automatico)
```

> Voce **nao** instala PHP/Phalcon na VM. E o `composer install` na VM e
> instantaneo, porque o projeto nao tem dependencias PHP externas (so extensoes,
> que ja vem na imagem).

## Pre-requisitos

- VM criada no Compute Engine (Ubuntu 22.04/24.04 ou Debian 12).
- IP externo da VM (de preferencia **IP estatico reservado**).
- Um dominio que voce controla.
- Regiao usada nos exemplos: `southamerica-east1` (Sao Paulo). Ajuste se quiser.

---

## Parte A — Preparar a imagem (no Cloud Shell)

Abra o **Cloud Shell** (icone `>_` no topo do console do GCP). Ele ja tem `gcloud`
e `git`, e roda no navegador.

### A.1 Habilitar as APIs

```bash
gcloud services enable artifactregistry.googleapis.com cloudbuild.googleapis.com
```

### A.2 Criar o repositorio de imagens

```bash
gcloud artifacts repositories create phalcon \
  --repository-format=docker \
  --location=southamerica-east1 \
  --description="Imagens do projeto Phalcon"
```

### A.3 Clonar o repositorio e compilar no Cloud Build

```bash
git clone https://github.com/SamuelDeveloperPHP/managect_project_phalcon.git
cd managect_project_phalcon
gcloud builds submit --config cloudbuild.yaml .
```

Ao terminar, a imagem estara publicada em:

```text
southamerica-east1-docker.pkg.dev/SEU_PROJETO/phalcon/app:latest
```

Guarde esse caminho — vai no `.env` da VM como `APP_IMAGE`. Para descobrir o ID do
projeto: `gcloud config get-value project`.

---

## Parte B — Deixar a VM baixar a imagem

A VM precisa de permissao de **leitura** no Artifact Registry.

### B.1 Dar a permissao a service account da VM (no Cloud Shell)

```bash
PROJECT=$(gcloud config get-value project)
PN=$(gcloud projects describe $PROJECT --format='value(projectNumber)')

gcloud projects add-iam-policy-binding $PROJECT \
  --member="serviceAccount:${PN}-compute@developer.gserviceaccount.com" \
  --role="roles/artifactregistry.reader"
```

### B.2 Garantir o escopo de acesso da VM

O token da VM precisa do escopo `cloud-platform`. Se voce criou a VM com
**"Permitir acesso total a todas as APIs do Cloud"**, pode pular este passo.

Caso contrario, ajuste (a VM precisa estar **parada**):

```bash
gcloud compute instances stop NOME_DA_VM --zone SUA_ZONA
gcloud compute instances set-service-account NOME_DA_VM --zone SUA_ZONA \
  --scopes=cloud-platform
gcloud compute instances start NOME_DA_VM --zone SUA_ZONA
```

> Alternativa sem mexer no escopo: criar uma service account so-de-leitura, gerar
> uma chave JSON e fazer `docker login -u _json_key --password-stdin` na VM. Evite
> chaves de longa duracao quando puder usar o escopo acima.

---

## Parte C — Firewall e DNS

### C.1 Abrir portas 80 e 443 (no Cloud Shell)

```bash
gcloud compute firewall-rules create allow-web \
  --allow tcp:80,tcp:443 \
  --direction INGRESS \
  --network default \
  --source-ranges 0.0.0.0/0
```

### C.2 Apontar o dominio

No painel do dominio, crie um registro **A** apontando para o IP externo da VM.
Faca isso **antes** de subir o Caddy, senao a emissao do certificado falha.

```text
Tipo: A    Nome: @ (ou app)    Valor: <IP_EXTERNO_DA_VM>
```

---

## Parte D — Rodar na VM

Conecte pelo botao **SSH** ao lado da VM no console. Os comandos abaixo rodam
**dentro da VM**.

### D.1 Instalar Docker e Git

```bash
sudo apt-get update
sudo apt-get install -y git
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
```

### D.2 Clonar o repositorio

```bash
git clone https://github.com/SamuelDeveloperPHP/managect_project_phalcon.git
cd managect_project_phalcon
```

### D.3 Configurar o .env

```bash
cp .env.example .env
nano .env
```

Ajuste:

```env
APP_ENV=production

DB_PASSWORD=<senha_forte_do_banco>
MYSQL_ROOT_PASSWORD=<outra_senha_forte>
ADMIN_EMAIL=seu-admin@dominio.com.br
ADMIN_PASSWORD=<senha_forte_do_admin>

DOMAIN=seudominio.com.br
ACME_EMAIL=voce@seudominio.com.br

# caminho da imagem publicada na Parte A (troque SEU_PROJETO)
APP_IMAGE=southamerica-east1-docker.pkg.dev/SEU_PROJETO/phalcon/app:latest
```

### D.4 Autenticar o Docker no Artifact Registry

Usa o token da propria VM (sem gcloud, sem chave):

```bash
curl -s -H "Metadata-Flavor: Google" \
  "http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token" \
  | grep -o '"access_token":"[^"]*' | cut -d'"' -f4 \
  | docker login -u oauth2accesstoken --password-stdin \
    https://southamerica-east1-docker.pkg.dev
```

Deve responder `Login Succeeded`.

### D.5 Subir tudo

```bash
docker compose -f compose.prod.yaml pull
docker compose -f compose.prod.yaml up -d
```

Acompanhe a emissao do certificado:

```bash
docker compose -f compose.prod.yaml logs -f caddy
```

### D.6 Autoload, migrations e seed (rapido — nada compila)

```bash
docker compose -f compose.prod.yaml exec app composer install --no-dev --optimize-autoloader
docker compose -f compose.prod.yaml exec app composer migrate
docker compose -f compose.prod.yaml exec app composer seed   # opcional: cria o admin
```

### D.7 Testar

```text
https://seudominio.com.br
```

O cadeado de HTTPS aparece sozinho. Login em `/login` com `ADMIN_EMAIL` /
`ADMIN_PASSWORD` do `.env`.

---

## Atualizacoes futuras

**Mudou so o codigo PHP** (controllers, views, models) — nao precisa recompilar
imagem:

```bash
# na VM
cd managect_project_phalcon
git pull
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml exec app composer install --no-dev --optimize-autoloader
docker compose -f compose.prod.yaml exec app composer migrate
```

**Mudou o Dockerfile** (versao do PHP, extensoes) — recompile a imagem:

```bash
# no Cloud Shell, dentro do repo
git pull
gcloud builds submit --config cloudbuild.yaml .

# depois, na VM
docker compose -f compose.prod.yaml pull
docker compose -f compose.prod.yaml up -d
```

---

## Seguranca

- MySQL, Redis e phpMyAdmin **nao** ficam expostos na internet nesta config.
- O `.env` esta no `.gitignore`; nunca faca commit dele.
- Use `APP_ENV=production` e senhas fortes.
- Reserve um **IP estatico** para a VM (dominio nao quebra em reinicios).
- Para administrar o banco, use o cliente dentro do container:

  ```bash
  docker compose -f compose.prod.yaml exec mysql \
    sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
  ```

- `mysql:5.7` esta em fim de vida; considere migrar para `mysql:8.0` no futuro.
