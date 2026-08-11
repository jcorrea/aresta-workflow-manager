#!/usr/bin/env bash
# deploy.sh — executado NO servidor Hostinger via SSH pelo GitHub Actions.
#
# Estrutura no servidor:
#   CORE_DIR (Privado): ~/apps/aresta-workflow
#   WEB_DIR  (Público): ~/domains/acertars.com.br/public_html/aresta-workflow
#
# Permissão: chmod +x deploy.sh

set -euo pipefail

# ── Configuração de Caminhos ──────────────────────────────────────────────────
# 1. Onde fica o código-fonte (fora de domains/)
CORE_DIR="$HOME/aresta-workflow"

# 2. Onde fica a pasta pública servida pelo Apache/LiteSpeed
WEB_DIR="$HOME/domains/acertars.com.br/public_html/aresta-workflow"

# Binários do PHP e Composer (Ajuste se necessário)
PHP=/usr/bin/php
COMPOSER_BIN=/usr/local/bin/composer

cd "$CORE_DIR"

echo "──────────────────────────────────────────"
echo " Deploy iniciado: $(date '+%Y-%m-%d %H:%M:%S')"
echo "──────────────────────────────────────────"

# ── 1. Validação do .env ─────────────────────────────────────────────────────
if [[ ! -f .env ]]; then
  echo "ERRO: arquivo .env não encontrado em $CORE_DIR"
  echo "      Crie-o a partir de .env.example e rode: php artisan key:generate"
  exit 1
fi

REQUIRED_VARS=(APP_KEY APP_ENV DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD)
MISSING=()

for VAR in "${REQUIRED_VARS[@]}"; do
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

DEBUG=$(grep -E '^APP_DEBUG=' .env | cut -d'=' -f2- | tr -d '"' | tr -d "'" | xargs | tr '[:upper:]' '[:lower:]')
if [[ "$DEBUG" == "true" ]]; then
  echo "AVISO: APP_DEBUG=true em produção. Considere desativar."
fi

echo " .env validado com sucesso."

# ── 2. Modo de manutenção ────────────────────────────────────────────────────
$PHP artisan down --retry=10 --render="errors::503"

trap '$PHP artisan up; echo "ERRO — modo de manutenção desativado automaticamente."' ERR

# ── 3. Atualizar código via Git ───────────────────────────────────────────────
git pull origin main --ff-only

# ── 4. Dependências PHP ───────────────────────────────────────────────────────
if ! $PHP "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts; then
  echo "AVISO: composer install saiu com erro (esperado por conta do proc_open desabilitado)."
fi
$PHP artisan package:discover --ansi

# ── 5. Migrations ─────────────────────────────────────────────────────────────
$PHP artisan migrate --force

# ── 6. Otimização de Assets e Filament ────────────────────────────────────────
$PHP artisan filament:assets
$PHP artisan filament:optimize

# ── 7. Sincronização e Patch do Webroot ───────────────────────────────────────
echo " Sincronizando arquivos públicos para $WEB_DIR..."

# Garante que a pasta pública de destino existe
mkdir -p "$WEB_DIR"

# Copia todo o conteúdo da pasta public/ do Laravel para o diretório Web
rsync -av --delete "$CORE_DIR/public/" "$WEB_DIR/"

# Aplica o patch no index.php do diretório Web para encontrar o CORE
echo " Ajustando caminhos de bootstrap no $WEB_DIR/index.php..."
sed -i "s|__DIR__\.'/../vendor|$CORE_DIR/vendor|g" "$WEB_DIR/index.php"
sed -i "s|__DIR__\.'/../bootstrap|$CORE_DIR/bootstrap|g" "$WEB_DIR/index.php"
sed -i "s|__DIR__\.'/../storage|$CORE_DIR/storage|g" "$WEB_DIR/index.php"

# ── 8. Storage Link Personalizado ─────────────────────────────────────────────
# Cria o link simbólico diretamente da pasta WEB para a pasta pública do Storage
if [[ -L "$WEB_DIR/storage" || -d "$WEB_DIR/storage" ]]; then
  rm -rf "$WEB_DIR/storage"
fi
ln -s "$CORE_DIR/storage/app/public" "$WEB_DIR/storage"
echo " Link simbólico do storage gerado com sucesso."

# ── 9. Otimização de Caches ───────────────────────────────────────────────────
$PHP artisan config:clear
$PHP artisan route:clear
$PHP artisan view:clear
$PHP artisan event:clear

$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

# ── 10. Permissões ────────────────────────────────────────────────────────────
chmod -R 775 storage bootstrap/cache
chmod -R 755 "$WEB_DIR"

# ── 11. Sair do modo de manutenção ───────────────────────────────────────────
$PHP artisan up

echo "──────────────────────────────────────────"
echo " Deploy concluído com sucesso: $(date '+%Y-%m-%d %H:%M:%S')"
echo "──────────────────────────────────────────"