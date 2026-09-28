<?php $firstType = array_key_first($S['types']); ?>
<!-- MODAL COMPRA -->
<div class="modal" id="buy-modal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="buy-title">
    <button class="modal-x" data-close aria-label="Fechar">×</button>

    <!-- passo 1: dados -->
    <form class="step-pane" data-step="1" id="buy-form">
      <h2 id="buy-title">Sua <?= e($S['item']) ?></h2>
      <?php if ($fraseCompra = frase('compra', $S['slug'])): ?><p class="piada compra-piada"><?= e($fraseCompra) ?></p><?php endif; ?>

      <!-- carrinho: aparece quando há itens adicionados -->
      <div class="cart" id="cart" hidden>
        <b class="cart-title">No seu pedido</b>
        <ul class="cart-list" id="cart-list"></ul>
        <p class="cart-next" id="cart-next">Preencha abaixo para adicionar mais uma, ou continue para o pagamento.</p>
      </div>

      <div class="olive-picker">
        <?php foreach ($S['types'] as $val => $t): ?>
          <label>
            <input type="radio" name="tipo" value="<?= $val ?>" <?= $val === $firstType ? 'checked' : '' ?>>
            <span>
              <?= item_svg($S, $val, '', min(1.3, $S['scales'][$val] ?? 1) * 1.1) ?>
              <?= e($t['label']) ?>
              <em class="pick-price"><?= money($t['price']) ?><small>/ano</small></em>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="qty-row">
        <span>Quantidade <small>(iguais, no mesmo nome)</small></span>
        <div class="qty">
          <button type="button" data-qty="-1" aria-label="Menos uma">−</button>
          <input type="number" name="qtd" id="qty-input" value="1" min="1" max="50" inputmode="numeric" aria-label="Quantidade">
          <button type="button" data-qty="1" aria-label="Mais uma">+</button>
        </div>
      </div>
      <div class="photo-row">
        <label class="photo-pick" title="Escolher foto">
          <input type="file" name="foto" accept="image/*" id="photo-input">
          <span class="photo-preview" id="photo-preview"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h3l2-2h6l2 2h3v12H4z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg></span>
          <small>Sua foto</small>
        </label>
        <label class="field"><span>Nome no certificado</span><input name="nome" required maxlength="28" placeholder="<?= $S['slug'] === 'esquerda' ? 'Ex.: Companheira Rita' : 'Ex.: Tio do Churrasco' ?>"></label>
      </div>
      <div class="selo-field">
        <b>Selo na <?= e($S['item']) ?> <small>(opcional)</small></b>
        <div class="selo-picker">
          <label title="Sem selo"><input type="radio" name="selo" value="" checked><span class="selo-opt selo-none">—</span></label>
          <?php foreach ($S['selos'] as $val => $label): ?>
            <label title="<?= e($label) ?>">
              <input type="radio" name="selo" value="<?= e($val) ?>">
              <span class="selo-opt"><?php if ($val === 'br'): ?><svg viewBox="-10 -10 20 20"><use href="#selo-br" x="-10" y="-10" width="20" height="20"/></svg><?php else: ?><?= e($val) ?><?php endif; ?></span>
            </label>
          <?php endforeach; ?>
          <input type="radio" name="selo" value="custom" id="selo-custom" hidden>
          <label class="selo-upload" title="Enviar imagem (ex.: bandeira do seu partido)">
            <input type="file" accept="image/*" id="selo-input">
            <span class="selo-opt" id="selo-upload-preview">+</span>
          </label>
        </div>
        <small class="selo-hint">Ou toque em <b>+</b> e envie a sua bandeira.</small>
      </div>
      <div class="row two">
        <label class="field"><span>Cidade</span><input name="cidade" required maxlength="30" placeholder="<?= $S['slug'] === 'esquerda' ? 'Ex.: Recife' : 'Ex.: Chapecó' ?>"></label>
        <label class="field"><span>UF</span>
          <select name="uf" required>
            <option value="">—</option>
            <?php foreach (UFS as $uf): ?><option><?= $uf ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <label class="field"><span>Sua frase (vai para o mural)</span><textarea name="frase" required maxlength="140" rows="2" placeholder="Ex.: <?= e(mock_phrases($S['slug'])[1]) ?>"></textarea></label>
      <div class="suggestions">
        <?php foreach (array_slice(mock_phrases($S['slug']), 0, 5) as $f): ?>
          <button type="button" class="chip" data-phrase="<?= e($f) ?>"><?= e($f) ?></button>
        <?php endforeach; ?>
      </div>
      <p class="nivel-nudge" id="nivel-nudge" aria-live="polite"></p>
      <div class="buy-actions">
        <button class="btn btn-ghost btn-block" type="button" id="add-more">+ Adicionar outra <?= e($S['item']) ?> <small>(outro tipo ou outra pessoa)</small></button>
        <button class="btn btn-gold btn-block" type="submit">Continuar · <span data-price-total><?= money($S['types'][$firstType]['price']) ?></span>/ano</button>
      </div>
    </form>

    <!-- passo 2: pagamento (simulado) -->
    <div class="step-pane" data-step="2" hidden>
      <h2>Pague com Pix</h2>
      <p class="muted">Protótipo: nenhuma cobrança é feita.</p>
      <div class="pix">
        <div class="qr" id="qr"></div>
        <div>
          <p class="price"><span data-price-total><?= money($S['types'][$firstType]['price']) ?></span><small>/ano</small></p>
          <p class="muted small"><b id="pix-summary">1 <?= e($S['item']) ?></b> no pote por 12 meses + certificados + direito de publicar no mural e comentar nos dois potes.</p>
          <button class="btn btn-ghost btn-sm" type="button" id="copy-pix">Copiar código Pix</button>
        </div>
      </div>
      <button class="btn btn-gold btn-block" id="simulate-pay">Simular pagamento aprovado</button>
      <button class="btn btn-link" data-back>← voltar</button>
    </div>

    <!-- passo 3: certificado -->
    <div class="step-pane" data-step="3" hidden>
      <h2><?= e($S['success']) ?></h2>
      <p class="nivel-up" id="nivel-up" hidden></p>
      <p class="muted" id="bought-summary" hidden></p>
      <ul class="bought-list" id="bought-list" hidden></ul>
      <div class="cert-slot" id="cert-slot"></div>
      <div class="share" id="share-buttons"></div>
    </div>
  </div>
</div>

<!-- MODAL RENOVAR -->
<div class="modal" id="renew-modal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="renew-title">
    <button class="modal-x" data-close aria-label="Fechar">×</button>
    <h2 id="renew-title">Renovar por mais 1 ano</h2>
    <div class="renew-body" id="renew-body"></div>
    <div class="pix">
      <div class="qr" id="renew-qr"></div>
      <div>
        <p class="price" id="renew-price"></p>
        <p class="muted small">Protótipo: nenhuma cobrança é feita.</p>
      </div>
    </div>
    <button class="btn btn-gold btn-block" id="renew-pay" type="button">Simular pagamento aprovado</button>
  </div>
</div>

<!-- MODAL VER CERTIFICADO -->
<div class="modal" id="cert-modal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-card" role="dialog" aria-modal="true">
    <button class="modal-x" data-close aria-label="Fechar">×</button>
    <div class="cert-slot" id="cert-view"></div>
    <div class="share" id="share-view"></div>
  </div>
</div>
