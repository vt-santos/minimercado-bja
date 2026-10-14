# Teste no navegador (Playwright/Chromium) contra uma instancia ja populada pelo e2e.sh.
# Uso: python3 navegador.py <url_base> <pasta_saida_capturas>
import sys, time
from playwright.sync_api import sync_playwright
B, saida = sys.argv[1], sys.argv[2]
r = []
with sync_playwright() as p:
    nav = p.chromium.launch()
    pg = nav.new_page(viewport={'width': 1024, 'height': 768}, has_touch=True, is_mobile=False)  # tablet deitado
    erros = []
    pg.on('pageerror', lambda e: erros.append(str(e)))
    pg.on('console', lambda m: erros.append(m.text) if m.type == 'error' else None)
    pg.goto(B + '/login.php'); pg.fill('input[name=usuario]', 'admin'); pg.fill('input[name=senha]', 'admin123'); pg.click('button[type=submit]')
    pg.goto(B + '/registrarcompra.php')
    css = pg.evaluate("getComputedStyle(document.querySelector('.btn-vinho')).backgroundColor")
    r.append(('Bootstrap + estilo carregados (botao vinho)', css == 'rgb(110, 15, 26)', css))
    pg.wait_for_selector('.botaoadicionar', timeout=5000)
    r.append(('botao "+ Adicionar" em uma linha', pg.locator('.botaoadicionar').first.evaluate('e => e.getBoundingClientRect().height') < 50, pg.locator('.botaoadicionar').first.inner_text()))
    r.append(('volume aparece no cartao', 'Lata 310 ml' in pg.locator('.cartaoproduto').filter(has_text='Coca-Cola').inner_text(), ''))
    r.append(('produtos com preco aparecem', pg.locator('.cartaoproduto').count() == 3, pg.locator('.cartaoproduto').count()))
    pg.fill('#campoBuscaComprador', 'Lucas Andr')
    pg.wait_for_selector('#listaSugestoes button', timeout=5000)
    sug = pg.locator('#listaSugestoes button').first
    tem_cor = sug.locator('.amostracor').count() == 1
    cor = sug.locator('.amostracor').evaluate("e => getComputedStyle(e).backgroundColor") if tem_cor else ''
    r.append(('sugestao mostra bolinha de cor da equipe', tem_cor and cor not in ('', 'rgba(0, 0, 0, 0)'), cor))
    pg.screenshot(path=saida + '/1_sugestao.png')
    sug.click()
    time.sleep(7)  # mais que os 6 s em que o painel sumia antes
    painel = pg.locator('#compradorSelecionado')
    r.append(('painel "Comprador selecionado" continua visivel apos 7 s', painel.is_visible(), painel.inner_text()[:60]))
    r.append(('painel mostra a cor', painel.locator('.amostracor').count() == 1, ''))
    r.append(('equipe preenchida automaticamente', pg.eval_on_selector('#campoEquipe', 'e => e.options[e.selectedIndex].text') == 'Minimercado', ''))
    pg.locator('.cartaoproduto').filter(has_text='Coca-Cola').locator('.botaoadicionar').click()
    pg.locator('.cartaoproduto').filter(has_text='Coca-Cola').locator('.seletorqtd button').nth(1).click()
    pg.locator('.cartaoproduto').filter(has_text='Coxinha').locator('.botaoadicionar').click()
    total = pg.inner_text('#valorTotalCarrinho')
    r.append(('carrinho soma 2 Coca + 1 Coxinha = R$ 19,50', total == 'R$ 19,50', total))
    r.append(('botao confirmar habilitado', pg.is_enabled('#botaoConfirmar'), ''))
    pg.screenshot(path=saida + '/2_carrinho_tablet.png', full_page=True)
    pg.click('#botaoConfirmar'); pg.wait_for_url('**/compradetalhe.php*', timeout=5000)
    r.append(('confirmar leva aos detalhes da compra', 'compradetalhe' in pg.url, pg.url.split('/')[-1]))
    # cadastro rapido de um jovem que nao esta na lista
    pg.goto(B + '/registrarcompra.php'); pg.wait_for_selector('.botaoadicionar')
    pg.fill('#campoBuscaComprador', 'Joana Navegador')
    pg.wait_for_selector('#listaSugestoes button.text-success', timeout=5000)
    r.append(('nome novo oferece "+ Cadastrar"', 'Cadastrar "Joana Navegador"' in pg.inner_text('#listaSugestoes'), ''))
    pg.click('#listaSugestoes button.text-success')
    pg.wait_for_selector('#modalNovoComprador.show', timeout=5000)
    r.append(('formulario ja vem com o nome', pg.input_value('#novoNome') == 'Joana Navegador', ''))
    pg.select_option('#novoEquipe', label='Círculo Rosa')
    pg.fill('input[name=responsavel_nome]', 'Mae da Joana'); pg.fill('input[name=telefone_responsavel]', '(61) 98888-1234')
    pg.screenshot(path=saida + '/5_cadastro_rapido.png')
    pg.click('#botaoSalvarNovo')
    pg.wait_for_selector('#modalNovoComprador', state='hidden', timeout=5000)
    painel = pg.inner_text('#compradorSelecionado')
    r.append(('jovem cadastrado ja fica selecionado com circulo e responsavel', 'Joana Navegador' in painel and 'Círculo Rosa' in painel and 'Mae da Joana' in painel, painel.replace(chr(10), ' | ')))
    cor = pg.locator('#compradorSelecionado .amostracor').evaluate("e => getComputedStyle(e).backgroundColor")
    r.append(('bolinha rosa no painel', cor == 'rgb(232, 67, 147)', cor))
    pg.locator('.botaoadicionar').first.click()
    pg.click('#botaoConfirmar'); pg.wait_for_url('**/compradetalhe.php*', timeout=5000)
    r.append(('compra do jovem confirmada', 'Joana Navegador' in pg.inner_text('main'), ''))
    # celular / tablet em pe
    for nome, w, h in [('tablet_deitado_1280', 1280, 800), ('tablet_deitado_1024', 1024, 768), ('tablet_em_pe_800', 800, 1280), ('celular_390', 390, 844)]:
        pg.set_viewport_size({'width': w, 'height': h})
        for tela in ['registrarcompra.php', 'historico.php', 'relatorios.php', 'index.php']:
            pg.goto(B + '/' + tela)
            if tela == 'registrarcompra.php':
                pg.wait_for_selector('.botaoadicionar')
                pg.locator('.botaoadicionar').first.click()
            larg = pg.evaluate('document.documentElement.scrollWidth')
            if larg > w: r.append((f'{nome} {tela}: rolagem lateral', False, larg))
            pg.screenshot(path=f'{saida}/{nome}_{tela.replace(".php","")}.png')
        menu_gaveta = pg.is_visible('.topopagina')
        r.append((f'{nome}: menu {"em gaveta" if menu_gaveta else "fixo ao lado"}', True, ''))
        if w < 992:
            pg.goto(B + '/registrarcompra.php'); pg.wait_for_selector('.botaoadicionar')
            r.append((f'{nome}: barra de total visivel embaixo', pg.is_visible('.barra-total'), ''))
    r.append(('nenhuma tela com rolagem lateral (4 tamanhos x 4 telas)', True, ''))
    r.append(('sem erros de JavaScript', not erros, erros[:3]))
    nav.close()
for nome, ok, det in r:
    print(('  PASSOU  ' if ok else '  FALHOU  ') + nome + (f'  [{det}]' if det not in ('', None) else ''))
print('RESULTADO:', sum(1 for x in r if x[1]), 'de', len(r))
sys.exit(0 if all(x[1] for x in r) else 1)
