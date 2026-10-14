document.addEventListener('DOMContentLoaded', function () {

  // Abrir/fechar menu lateral em telas pequenas
  var botaoMenu = document.querySelector('.botao.abrirmenu');
  var barraLateral = document.querySelector('.barra');
  var fundoMenu = document.querySelector('.fundomenu');

  if (botaoMenu && barraLateral) {
    botaoMenu.style.display = 'inline-flex';

    function alternarMenu(abrir) {
      barraLateral.classList.toggle('aberta', abrir);
      if (fundoMenu) {
        fundoMenu.classList.toggle('visivel', abrir);
      }
      document.body.style.overflow = abrir ? 'hidden' : '';
    }

    botaoMenu.addEventListener('click', function () {
      alternarMenu(!barraLateral.classList.contains('aberta'));
    });

    if (fundoMenu) {
      fundoMenu.addEventListener('click', function () {
        alternarMenu(false);
      });
    }

    document.querySelectorAll('.barra a.item').forEach(function (link) {
      link.addEventListener('click', function () {
        alternarMenu(false);
      });
    });
  }

  // Fecha automaticamente apenas mensagens de sucesso ("salvo com sucesso" etc.).
  // Avisos, erros e paineis da tela (ex.: comprador selecionado) ficam visiveis.
  document.querySelectorAll('.alert.alert-success:not(.alerta-fixo)').forEach(function (alerta) {
    setTimeout(function () {
      alerta.style.display = 'none';
    }, 6000);
  });
});

// Formata numero para o padrao monetario brasileiro
function formatarMoedaBr(valor) {
  return 'R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
