// =====================================================================
// VIP Imports — Máscara de moeda (padrão brasileiro)
// Formata em tempo de digitação: vírgula + 2 casas decimais,
// pontos nos milhares. Ex.: 52345 -> 523,45 | 1234 -> 12,34
//
// Uso:
//   <input name="valor" data-mask-moeda data-mask-int="9">
//   data-mask-int (opcional) = quantidade máxima de dígitos inteiros.
//
// Implementação por DELEGAÇÃO de eventos: funciona também para campos
// criados dinamicamente (ex.: linhas do bloco de compras).
// =====================================================================
(function () {
  'use strict';

  function formatar(input) {
    var maxInt = parseInt(input.getAttribute('data-mask-int') || '9', 10);
    var digitos = (input.value || '').replace(/\D/g, '');
    if (digitos === '') {
      input.value = '';
      return;
    }

    digitos = digitos.slice(0, maxInt * 2);
    var centavos = parseInt(digitos, 10);
    var reais = Math.floor(centavos / 100);
    var fracao = String(centavos % 100).padStart(2, '0');
    var inteiro = String(reais).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    input.value = inteiro + ',' + fracao;

    try {
      input.setSelectionRange(input.value.length, input.value.length);
    } catch (e) {
      /* input fora do DOM ou não editável */
    }
  }

  document.addEventListener('input', function (e) {
    var el = e.target;
    if (el && el.hasAttribute && el.hasAttribute('data-mask-moeda')) {
      formatar(el);
    }
  }, true);

  document.addEventListener('blur', function (e) {
    var el = e.target;
    if (el && el.hasAttribute && el.hasAttribute('data-mask-moeda')) {
      formatar(el);
    }
  }, true);
})();