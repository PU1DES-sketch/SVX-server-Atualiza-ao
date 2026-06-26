# Atualizacoes do repetidor

## Ideia

O Raspberry do cliente nao usa usuario nem senha do GitHub. Ele apenas le arquivos publicos:

- `releases/manifest.json`
- `releases/vX.Y.tar.gz`

Quem precisa de senha/token e somente quem publica a atualizacao no GitHub.

## Fluxo no cliente

1. A tela `update.php` chama `/usr/local/bin/repeater-update-check`.
2. O comando baixa o `manifest.json` publico do GitHub.
3. Se houver versao nova, a tela mostra titulo e descricao.
4. Ao clicar em atualizar, `repeater-update-apply` baixa o pacote `.tar.gz`.
5. O SHA256 do pacote e conferido antes de instalar.
6. O `install.sh` do pacote aplica a atualizacao e reinicia servicos necessarios.

## Publicacao de nova versao

1. Criar pasta `releases/vX.Y`.
2. Colocar `install.sh` e arquivos dentro de `releases/vX.Y/files`.
3. Gerar `releases/vX.Y.tar.gz`.
4. Calcular SHA256.
5. Atualizar `releases/manifest.json`.
6. Enviar para o GitHub.

## Versao 2.6

Inclui:

- opcao na web para habilitar/desabilitar fala da hora;
- timer `repeater-time-announce.timer`;
- script `/usr/local/bin/repeater_time_announce.py`;
- reproducao pelo evento local do SvxLink somente quando a repetidora estiver em repouso;
- tela web basica de atualizacoes;
- comandos `repeater-update-check` e `repeater-update-apply`.

