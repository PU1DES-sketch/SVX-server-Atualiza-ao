#!/bin/sh
set -eu

# Instala somente a base de configuracao do MultiLink Lab. Nenhum servico RF
# e alterado ou reiniciado por este instalador.
ROOT="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
install -d -m 0755 /etc/repeater /usr/local/bin /var/www/html/repeater
if [ ! -f /etc/repeater/links.json ]; then
  install -m 0640 "$ROOT/files/etc/repeater/links.json" /etc/repeater/links.json
fi
install -m 0755 "$ROOT/files/usr/local/bin/repeater-multilink-save" /usr/local/bin/repeater-multilink-save
install -m 0644 "$ROOT/files/var/www/html/repeater/links.php" /var/www/html/repeater/links.php
install -m 0644 "$ROOT/files/var/www/html/repeater/links-save.php" /var/www/html/repeater/links-save.php

if ! grep -q 'repeater-multilink-save' /etc/sudoers.d/repeater-panel 2>/dev/null; then
  printf '%s\n' 'www-data ALL=(root) NOPASSWD: /usr/local/bin/repeater-multilink-save' >> /etc/sudoers.d/repeater-panel
  chmod 0440 /etc/sudoers.d/repeater-panel
fi

# Add the entry without replacing the existing repeater panel. The marker
# makes this operation idempotent on a re-install.
PANEL=/var/www/html/repeater/index.php
if [ -f "$PANEL" ] && ! grep -q "links.php.*MULTILINK" "$PANEL"; then
  sed -i '/ATUALIZAR PAINEL<\/div>/a\        <div class="menu-item" onclick="window.location.href=\x27links.php\x27">MULTILINK LAB</div>' "$PANEL"
fi

echo 'MultiLink Lab instalado. Nenhum Link RF foi ativado.'
