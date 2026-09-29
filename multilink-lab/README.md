# ProtoRadio MultiLink Lab

Linha de desenvolvimento separada para o Raspberry de bancada `192.168.6.242`.
Nenhum arquivo desta pasta deve entrar no manifesto de atualizacao dos repetidores
em producao antes de testes completos com radios e placas USB.

## Objetivo inicial

Manter a Repetidora Master como esta e adicionar ate tres interfaces RF de link:

- Link 1: primeira placa de audio USB e par COS/PTT independente.
- Link 2: segunda placa de audio USB e par COS/PTT independente.
- Link 3: terceira placa de audio USB e par COS/PTT independente.

Cada link tera sua propria interface de audio, ganhos, GPIO, TOT e rota. O
indicativo, identificacao e atualizacoes permanecem centralizados no Master.

## Fluxo de audio inicial

1. Master recebe RF: pode encaminhar ao Link 1, 2 e/ou 3 habilitados.
2. Link recebe RF: pode encaminhar ao Master e aos outros links selecionados.
3. A origem nunca e retransmitida de volta para a mesma interface.
4. Cada transmissao recebe um identificador de origem para impedir ciclos
   A -> B -> A e A -> B -> C -> A.

## Base de configuracao

O arquivo e `/etc/repeater/links.json`. O painel real esta em `files/` e pode
ser instalado manualmente na bancada por `sudo ./install-lab.sh`. Ele valida e
grava as configuracoes, mas nao altera o SvxLink, nao inicia servicos e nao
aciona PTT/COS. A ativacao RF sera uma etapa posterior, depois de identificar
as placas USB e testar cada radio isoladamente.
