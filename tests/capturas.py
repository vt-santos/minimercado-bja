# Gera as capturas de tela do README a partir de uma instancia populada com popular_demo.sh.
# Uso: python3 capturas.py <url_base> <pasta_saida>
import sys
import time
from playwright.sync_api import sync_playwright

B, SAIDA = sys.argv[1], sys.argv[2]

with sync_playwright() as p:
    nav = p.chromium.launch()
    pg = nav.new_page(viewport={'width': 1280, 'height': 800}, has_touch=True)
    pg.goto(B + '/login.php')
    pg.screenshot(path=f'{SAIDA}/08_login.png')
    pg.fill('input[name=usuario]', 'admin')
    pg.fill('input[name=senha]', 'demo-portfolio')
    pg.click('button[type=submit]')

    pg.goto(B + '/index.php')
    pg.screenshot(path=f'{SAIDA}/01_painel.png')

    # Registrar compra: comprador com a cor da equipe + carrinho
    pg.goto(B + '/registrarcompra.php')
    pg.wait_for_selector('.botaoadicionar')
    pg.fill('#campoBuscaComprador', 'Lucas Andr')
    pg.wait_for_selector('#listaSugestoes button.list-group-item-action')
    pg.locator('#listaSugestoes button.list-group-item-action').first.click()
    for nome, vezes in [('Coca-Cola', 2), ('Coxinha', 1), ('Picolé', 1)]:
        cartao = pg.locator('.cartaoproduto').filter(has_text=nome).first
        cartao.locator('.botaoadicionar').click()
        for _ in range(vezes - 1):
            cartao.locator('.seletorqtd button').nth(1).click()
    pg.evaluate("window.scrollTo({top: 0, behavior: 'instant'})")
    pg.mouse.move(5, 790)
    time.sleep(0.3)
    pg.screenshot(path=f'{SAIDA}/02_registrar_compra.png')

    # Cadastro rapido de jovem (circulo)
    pg.goto(B + '/registrarcompra.php')
    pg.wait_for_selector('.botaoadicionar')
    pg.fill('#campoBuscaComprador', 'Pedro Visitante')
    pg.wait_for_selector('#listaSugestoes button.text-success')
    pg.click('#listaSugestoes button.text-success')
    pg.wait_for_selector('#modalNovoComprador.show')
    pg.select_option('#novoEquipe', label='Círculo Verde')
    pg.fill('input[name=responsavel_nome]', 'Responsável Fictício')
    pg.fill('input[name=telefone_responsavel]', '(61) 90002-0001')
    time.sleep(0.8)  # espera a animacao de abertura terminar
    pg.screenshot(path=f'{SAIDA}/03_cadastro_rapido.png')

    pg.goto(B + '/relatorios.php')
    pg.screenshot(path=f'{SAIDA}/04_relatorios.png')

    pg.goto(B + '/compradores.php')
    pg.screenshot(path=f'{SAIDA}/05_compradores.png')

    pg.goto(B + '/historico.php')
    pg.screenshot(path=f'{SAIDA}/07_historico.png')

    # Celular: menu em gaveta, dois produtos por linha e barra de total
    pg.set_viewport_size({'width': 390, 'height': 844})
    pg.goto(B + '/registrarcompra.php')
    pg.wait_for_selector('.botaoadicionar')
    pg.fill('#campoBuscaComprador', 'Ana Clara')
    pg.wait_for_selector('#listaSugestoes button.list-group-item-action')
    pg.locator('#listaSugestoes button.list-group-item-action').first.click()
    pg.locator('.cartaoproduto').filter(has_text='Picolé').first.locator('.botaoadicionar').click()
    pg.locator('.cartaoproduto').filter(has_text='Guaraná').first.locator('.botaoadicionar').click()
    pg.evaluate("window.scrollTo({top: 0, behavior: 'instant'})")
    time.sleep(0.3)
    pg.screenshot(path=f'{SAIDA}/06_celular.png')
    nav.close()
print('capturas geradas em', SAIDA)
