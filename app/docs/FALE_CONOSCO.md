# Página Fale Conosco

## Rotas

- `GET /fale-conosco`: renderiza a página.
- `POST /fale-conosco/enviar`: valida os campos, registra um log e redireciona com `success=1`.

## Arquivos

- View: `app/Views/pages/fale_conosco.php`
- Controller: `app/Controllers/InstitutionalController.php` (`faleConosco`, `enviarFaleConosco`)
- Rotas: `public_html/index.php`
- Log de mensagens: `app/logs/contact_messages.jsonl`

## Campos do formulário

- `name` (obrigatório)
- `email` (obrigatório, validado com `FILTER_VALIDATE_EMAIL`)
- `company` (obrigatório)
- `message` (obrigatório)

## SEO e acessibilidade

- `title` e `description` via `metaTitle`/`metaDescription`.
- Schema JSON-LD `LocalBusiness` incluído na view.
- Labels associadas por `for/id`, estados de erro com `aria-invalid` e bloco de alerta.

## Deploy

- Garanta permissões de escrita em `app/logs/` para registrar `contact_messages.jsonl`.
