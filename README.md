# Top Açaí Delivery — pedidos online

Loja virtual responsiva em PHP, MySQL e JavaScript. O cardápio vem do banco de dados, a sacola fica salva no navegador e os pedidos são validados e precificados no servidor. Pagamentos online por Pix e cartão de crédito são processados pela SyncPay; os demais pedidos são enviados ao WhatsApp da loja.

## Requisitos

- PHP 8.1 ou superior com a extensão `pdo_mysql` e acesso HTTPS de saída
- MySQL 8.0 ou superior
- Servidor web configurado para executar PHP (Apache, Nginx ou servidor embutido)

## Configuração

1. Crie o banco e as tabelas com `mysql -u root -p < database/acaidelivery-schema.sql`.
2. Se já instalou o banco anteriormente, aplique `mysql -u root -p < database/admin-migration.sql` para adicionar as tabelas de acesso do painel.
3. Se a loja já estava instalada, aplique `mysql -u root -p < database/tracking-migration.sql` para habilitar links de acompanhamento nos novos pedidos e `mysql -u root -p < database/product-notes-migration.sql` para salvar observações individuais dos produtos.
4. Configure as variáveis de ambiente do PHP no `.env` (copiado de `.env.example`): `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD`. Os padrões locais são `127.0.0.1`, `3306`, `acaidelivery_db` e `pedidos_app`; defina a senha do usuário MySQL local em `DB_PASSWORD`.
5. Para habilitar pagamentos Pix online em uma instalação existente, aplique `mysql -u root -p < database/online-payments-migration.sql`. Preencha `SYNCPAY_CLIENT_ID`, `SYNCPAY_CLIENT_SECRET`, `SYNCPAY_WEBHOOK_SECRET` e `APP_BASE_URL` no arquivo `.env` na raiz do projeto. Não é necessário preencher `SYNCPAY_PUBLIC_KEY` enquanto o cartão online estiver pausado. O `.env` é local, está no `.gitignore` e não deve ser enviado ao repositório.
6. Cadastre na SyncPay o webhook para o evento `transaction.updated` com a URL `https://seu-dominio/api/syncpay-webhook.php`, usando o segredo de assinatura correspondente em `SYNCPAY_WEBHOOK_SECRET`. Para Pix, são necessárias as credenciais da API, o segredo do webhook e a URL pública HTTPS. O cartão online está pausado; crédito, débito e dinheiro na entrega/retirada continuam disponíveis.
7. Crie a primeira conta administrativa, com uma senha de pelo menos 12 caracteres: `php database/create-admin.php seu_usuario`. O script pede a senha sem exibi-la no terminal.
8. Se esquecer a senha de uma conta existente, redefina-a localmente com `php database/reset-admin-password.php seu_usuario`. O script pede a nova senha sem exibi-la e encerra as sessões antigas dessa conta. Não passe a senha como argumento do comando.
9. Sirva a pasta pela raiz de um servidor PHP. Para desenvolvimento, na raiz do projeto, use `php -S 127.0.0.1:8000 dev-server.php`; depois abra `http://127.0.0.1:8000` para a loja ou `http://127.0.0.1:8000/admin/` para o painel.
10. No painel, entre com sua conta para cadastrar, editar, ocultar, reativar ou excluir produtos; acompanhar e atualizar pedidos; imprimir uma comanda térmica de entrega ou retirada pelo botão **Imprimir comanda**; configurar o nome, WhatsApp, funcionamento e taxas da loja; e criar ou desativar outros acessos administrativos.
11. Edite as informações iniciais em `store_config` para configurar o número real da empresa com código do país e DDD, apenas dígitos (por exemplo, `5511988887777`). Personalize também nome e taxas de entrega.
12. Remova a loja de demonstração e os produtos de exemplo antes de publicar.

### Teste local no WSL

1. Aplique a migração com uma conta MySQL administrativa: `sudo mysql < database/online-payments-migration.sql`. O usuário `pedidos_app` da aplicação não tem permissão para alterar o esquema.
2. A SyncPay documenta a API no host de produção `https://api.syncpayments.com.br`; não há um host de sandbox descrito nesta integração. Confirme com a SyncPay se sua conta e credenciais estão em modo de teste antes de fazer qualquer cobrança.
3. Abra um túnel HTTPS público apontando para a porta local do PHP. O webhook não consegue acessar `localhost` dentro do WSL; configure o endereço HTTPS do túnel em `APP_BASE_URL`, sem barra no final.
4. Preencha `.env` na raiz do projeto com as credenciais do painel e o endereço atual do túnel. Não compartilhe esse arquivo. Depois, inicie o servidor PHP na raiz do projeto:

   ```bash
   cd /var/www/html/web-pedidos
   php -S 0.0.0.0:8181 dev-server.php
   ```

5. Recarregue a loja. O Pix online só será habilitado quando a migração, as credenciais e o segredo do webhook estiverem configurados. O cartão online está desabilitado nesta versão e não exige chave pública. Faça cobranças apenas após confirmar o ambiente da conta; o túnel precisa permanecer ativo para o webhook.

Se a aplicação estiver atrás de um proxy reverso, configure `SYNCPAY_TRUSTED_PROXY_IPS` com os endereços IP exatos dos proxies separados por vírgula. O proxy deve sobrescrever ou sanitizar `X-Real-IP`/`X-Forwarded-For`; esses cabeçalhos só são usados quando a conexão vem de um proxy explicitamente confiável.

O projeto carrega automaticamente as variáveis do arquivo `.env` na inicialização da API. As variáveis já definidas no ambiente do PHP têm prioridade. Para Apache, o `.htaccess` bloqueia o acesso HTTP a `.env`; o servidor PHP embutido deve ser iniciado com `dev-server.php`, que também bloqueia esses arquivos.

Se o Apache no WSL roda como `www-data` e não consegue ler `.env`, conceda acesso somente ao grupo do servidor web:

```bash
sudo chgrp www-data .env
sudo chmod 640 .env
```

Não use `chmod 777` nem torne o arquivo legível por todos. Se o PHP-FPM/Apache usar outro usuário ou grupo, substitua `www-data` pelo grupo correto do processo PHP.

> O número inicial no SQL é apenas um exemplo. Configure um WhatsApp válido da empresa antes de testar o envio de pedidos.
>
> Em produção, publique a loja e o painel apenas com HTTPS, use uma conta MySQL dedicada com permissões mínimas e mantenha suas credenciais do banco fora do código-fonte.

## Como funciona

- A coleção Postman com todas as rotas da API está em `postman/Web-Pedidos.postman_collection.json`. Importe-a no Postman e ajuste `baseUrl` para a URL da instalação (por exemplo, `http://localhost/web-pedidos`). Para o painel, execute primeiro **Obter sessão e CSRF**, depois **Login administrativo**; o Postman manterá o cookie de sessão. Preencha credenciais apenas em variáveis locais e não exporte segredos. O exemplo de Pix online pode iniciar uma cobrança real na SyncPay.
- Rotas da API: `GET /api/menu.php`; `POST /api/orders.php`; `POST /api/track-order.php`; `GET` e `POST /api/admin.php` (login, logout, produtos, loja, pedidos e contas administrativas); `POST /api/syncpay-webhook.php` (callback assinado da SyncPay).

- `GET /api/menu.php` retorna as informações da loja, categorias e produtos disponíveis.
- `POST /api/orders.php` recebe cliente, entrega/retirada, pagamento e itens; busca novamente os produtos e calcula valores no servidor antes de salvar o pedido e seus itens em uma transação.
- Ao tocar em um produto, o cliente confere os detalhes, informa observações, escolhe a quantidade e só então adiciona à sacola. As observações específicas de cada item aparecem na mensagem do WhatsApp e na comanda do painel.
- No checkout, telefone e CPF recebem máscara durante a digitação; os valores são enviados à API somente com dígitos. O Pix apresenta um QR Code gerado localmente no navegador e o código copia e cola. Cartão de crédito online está pausado; dinheiro, crédito e débito na entrega/retirada continuam disponíveis. E-mail e CPF são coletados somente para pagamentos Pix e enviados ao provedor sem serem gravados no banco local. O webhook valida a assinatura HMAC e atualiza o pagamento; o painel só permite confirmar um pedido online depois da aprovação.
- Cada novo pedido inclui um link privado para `acompanhar.php`; a página consulta somente o status e o atualiza automaticamente enquanto está aberta. No painel, para entrega, avance de **Preparando** para **Confirmar saída para entrega** e, quando o motoboy retornar após concluir a entrega, para **Confirmar entrega (motoboy retornou)**. Para retirada, os botões indicam quando o pedido está pronto e quando o cliente o retirou. A API usa um token aleatório, armazena apenas seu hash e não exige que o cliente informe dados pessoais. Pedidos anteriores à migração não têm link de acompanhamento.
- `/admin/` é o painel administrativo, com autenticação por contas armazenadas na tabela `admin_users`, limite de tentativas de acesso, sessões protegidas e token CSRF para operações de escrita. Os pedidos são atualizados automaticamente a cada 15 segundos enquanto a página está visível, com aviso na tela para novos pedidos. Senhas são armazenadas com `password_hash`; o cadastro da primeira conta é feito exclusivamente pela linha de comando.
- Cada pedido do painel pode abrir uma comanda formatada para bobina de 58 mm, adequada à POS-58, na janela de impressão do navegador. Selecione a POS-58, configure papel/bobina de 58 mm e margens mínimas ou sem margens nas preferências da impressora, desative cabeçalhos e rodapés do navegador e confirme a impressão. A área útil da comanda está configurada em 48 mm para acomodar as margens físicas comuns dessas impressoras. O computador da loja precisa ter a impressora instalada; a impressão é iniciada manualmente pelo painel e não ocorre automaticamente em segundo plano. Se o driver apresentar corte ou muito espaço em branco, ajuste o tamanho de papel e margens nas preferências do driver.
- Para pagamentos na entrega/retirada, após o registro bem-sucedido o navegador abre uma conversa `wa.me` com os detalhes e o identificador do pedido. O cliente ainda precisa tocar em **Enviar** no WhatsApp para concluir a comunicação com a loja. Pedidos online ficam registrados no painel e mostram o código Pix ou o estado da autorização do cartão no site.
- O link de acompanhamento aparece na confirmação do site e na mensagem de WhatsApp preparada para envio. Isso não envia notificações automáticas ao cliente: para mensagens proativas de mudança de status, é necessário integrar a WhatsApp Business API ou outro provedor.
- A taxa de entrega não é cobrada para retirada; o valor configurado fica grátis a partir do mínimo configurado, se houver. Produtos, preços, disponibilidade e configurações da loja são mantidos nas tabelas `products` e `store_config`.

O total e os preços enviados pelo navegador nunca são usados para cobrar o pedido: a API calcula os valores a partir do banco. A SyncPay processa Pix online; as demais formas são pagas diretamente na entrega ou retirada.
