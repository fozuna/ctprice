# 🌐 Acesso ao Sistema pela Rede Interna

## 📋 Instruções para a Equipe

### 🔗 Como Acessar o Sistema

1. **Obtenha o IP atual** do servidor:
   - Acesse: `http://IP_DO_SERVIDOR:8000/network-info.php`
   - Ou pergunte ao administrador do sistema

2. **URL de Acesso**:
   ```
   http://IP_ATUAL:8000
   ```

### 📱 Exemplo de Uso

Se o IP atual for `172.250.40.78`, acesse:
```
http://172.250.40.78:8000
```

### ⚠️ Pontos Importantes

- **IP Dinâmico**: O IP pode mudar diariamente
- **Verificação**: Sempre confirme o IP atual antes de acessar
- **Rede Interna**: Funciona apenas dentro da rede da empresa
- **Porta**: O sistema roda na porta 8000

### 🔧 Para o Administrador

#### Iniciar o Servidor:
```bash
# Opção 1: Usar o script automático
start-server.bat

# Opção 2: Comando manual
C:\xampp\php\php.exe -S 0.0.0.0:8000
```

#### Verificar IP Atual:
```bash
ipconfig | findstr "IPv4"
```

#### Compartilhar com a Equipe:
1. Execute o servidor
2. Obtenha o IP atual
3. Compartilhe a URL: `http://IP_ATUAL:8000`

### 🛠️ Soluções para IP Dinâmico

#### Opção 1: IP Estático (Recomendado)
- Configure um IP estático no roteador/DHCP
- Solicite ao TI da empresa

#### Opção 2: DNS Local
- Configure um nome DNS local (ex: `metas.empresa.local`)
- Solicite ao TI da empresa

#### Opção 3: Verificação Diária
- Sempre verifique o IP antes de compartilhar
- Use a página `network-info.php`

### 🔒 Configurações de Firewall

Se houver problemas de acesso, verifique:

1. **Windows Firewall**:
   - Permita conexões na porta 8000
   - Ou desabilite temporariamente para teste

2. **Antivírus**:
   - Adicione exceção para a porta 8000
   - Ou para o diretório do projeto

### 📞 Suporte

Em caso de problemas:
1. Verifique se o servidor está rodando
2. Confirme o IP atual
3. Teste o acesso local primeiro (`localhost:8000`)
4. Verifique configurações de firewall

---

**Última atualização**: $(Get-Date -Format "dd/MM/yyyy HH:mm")
**Administrador**: Sistema de Gestão de Metas - Madeplant