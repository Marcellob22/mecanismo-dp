# Mecanismo DP

Site interno do DP (PHP + MySQL). Tudo que está na pasta `site/` é enviado
automaticamente para `public_html` na Locaweb a cada alteração na branch `main`.

- O arquivo `config.php` (senhas) NUNCA fica no repositório; ele existe só no servidor.
- O deploy não apaga arquivos do servidor, apenas envia/atualiza.
- Segredos necessários no GitHub (Settings > Secrets and variables > Actions): FTP_HOST, FTP_USER, FTP_PASSWORD.
