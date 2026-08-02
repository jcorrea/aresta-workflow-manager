#!/usr/bin/env bash
# deploy.sh — executado NO servidor Hostinger (shared hosting) via SSH pelo GitHub Actions.
#
# Localização no servidor: ~/aresta-workflow/deploy.sh
#
# Estrutura esperada no servidor:
#   ~/aresta-workflow/        ← código Laravel (document root = ./public)
#   ├── deploy.sh             ← este arquivo
#   ├── public/               ← webroot (configurado no hPanel como Document Root do subdomínio)
#   │   ├── index.php
#   │   └── build/            ← assets Vite (enviados pelo GitHub Actions via SCP)
#   ├── .env
#   ├── artisan
#   └── ...
#
# Configuração no hPanel da Hostinger:
#   Subdomínios → workflow.seusite.com → Editar → Document Root:
#   /home/USUARIO/aresta-workflow/public
#
# Permissão: chmod +x deploy.sh

set -euo pipefail

# ── Configuração ──────────────────────────────────────────────────────────────
APP_DIR=~/aresta-workflow
PHP="php"   # Na Hostinger shared, 'php' aponta para a versão configurada no hPanel.
            # Se precisar de versão específica: /usr/local/bin/php8.3

cd "$APP_DIR"

echo "──────────────────────────────────────────"
echo " Deploy iniciado: $(date '+%Y-%m-%d %H:%M:%S')"
echo "──────────────────────────────────────────"

# ── 1. Validação do .env ─────────────────────────────────────────────────────
if [[ ! -f .env ]]; then
  echo "ERRO: arquivo .env não encontrado em $APP_DIR"
  echo "      Crie-o a partir de .env.example e rode: php artisan key:generate"
  exit 1
fi

# Variáveis obrigatórias para a app funcionar em produção
REQUIRED_VARS=(APP_KEY APP_ENV DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD)
MISSING=()

for VAR in "${REQUIRED_VARS[@]}"; do
  # Extrai o valor da variável no .env (ignora linhas comentadas)
  VALUE=$(grep -E "^${VAR}=" .env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs)
  if [[ -z "$VALUE" ]]; then
    MISSING+=("$VAR")
  fi
done

if [[ ${#MISSING[@]} -gt 0 ]]; then
  echo "ERRO: as seguintes variáveis estão vazias ou ausentes no .env:"
  for VAR in "${MISSING[@]}"; do
    echo "  - $VAR"
  done
  exit 1
fi

# Garante que não estamos em debug mode em produção
DEBUG=$(grep -E '^APP_DEBUG=' .env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs | tr '[:upper:]' '[:lower:]')
if [[ "$DEBUG" == "true" ]]; then
  echo "AVISO: APP_DEBUG=true em produção. Considere desativar."
fi

echo " .env validado com sucesso."

# ── 2. Modo de manutenção ────────────────────────────────────────────────────
# SHARED HOSTING: 'artisan down' cria storage/framework/maintenance.php — não
# precisa de root; basta permissão de escrita na pasta storage (já é do usuário).
$PHP artisan down --retry=10 --render="errors::503"

trap '$PHP artisan up; echo "ERRO — modo de manutenção desativado automaticamente."' ERR

# ── 3. Atualizar código via Git ───────────────────────────────────────────────
git pull origin main --ff-only

# ── 4. Dependências PHP ───────────────────────────────────────────────────────
# SHARED HOSTING: 'composer' geralmente está no PATH do SSH da Hostinger.
# Se não estiver: use 'php composer.phar' com o composer.phar na raiz do projeto.
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ── 5. Migrations ─────────────────────────────────────────────────────────────
$PHP artisan migrate --force

# ── 6. Limpar e re-otimizar caches ────────────────────────────────────────────
$PHP artisan config:clear
$PHP artisan route:clear
$PHP artisan view:clear
$PHP artisan event:clear

$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

$PHP artisan filament:optimize

# ── 7. Permissões ─────────────────────────────────────────────────────────────
# SHARED HOSTING: sem sudo, sem chown www-data — o usuário SSH é o mesmo dono
# dos arquivos executados pelo servidor web. Apenas garante permissões de escrita.
chmod -R 775 storage bootstrap/cache

# ── 8. Sair do modo de manutenção ────────────────────────────────────────────
$PHP artisan up

echo "──────────────────────────────────────────"
echo " Deploy concluído: $(date '+%Y-%m-%d %H:%M:%S')"
echo "──────────────────────────────────────────"
