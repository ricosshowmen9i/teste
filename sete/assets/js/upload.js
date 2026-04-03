/**
 * WhatsappJUJU — Upload de arquivos (upload.js)
 */

const Upload = (() => {
  // ── Upload de arquivo ─────────────────────────────────────
  function uploadFile(file, type = 'file') {
    if (!file) return;

    const $bar = showUploadProgress();

    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);

    return $.ajax({
      url:         'api/upload.php',
      type:        'POST',
      data:        formData,
      processData: false,
      contentType: false,
      xhr: () => {
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = e => {
          if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            $bar.find('.upload-bar-fill').css('width', pct + '%');
          }
        };
        return xhr;
      },
      success: data => {
        hideUploadProgress($bar);
        if (data.success) {
          setPendingFile(data.url, data.original_name, data.message_type);
          App.showToast('Arquivo pronto para enviar!', 'success');
        } else {
          App.showToast(data.error || 'Erro no upload', 'error');
        }
      },
      error: () => {
        hideUploadProgress($bar);
        App.showToast('Erro ao enviar arquivo', 'error');
      },
    });
  }

  // ── Configura arquivo pendente na input bar ───────────────
  function setPendingFile(url, name, type) {
    // Delega ao App para que sendMessage() encontre o arquivo
    App.setPendingFile(url, name, type || 'file');

    // Mostra preview acima da input area (antes de #input-bar)
    const icon = type === 'image' ? 'fa-image' : 'fa-file';
    const $preview = $(`
      <div id="pending-file-preview" style="display:flex;align-items:center;gap:8px;padding:6px 16px;
        background:rgba(0,0,0,0.06);font-size:13px;border-radius:8px;margin:0 8px 6px;">
        <i class="fas ${icon}"></i>
        <span>${App.escHtml(name)}</span>
        <button id="remove-pending-file" title="Remover"><i class="fas fa-times"></i></button>
      </div>
    `);
    $('#pending-file-preview').remove();
    $preview.insertBefore('#input-bar');

    $('#remove-pending-file').on('click', clearPendingFile);
  }

  function clearPendingFile() {
    App.clearPendingFile();
  }

  // ── Barra de progresso ────────────────────────────────────
  function showUploadProgress() {
    const $bar = $(`
      <div class="upload-progress-bar" style="position:fixed;bottom:70px;left:50%;transform:translateX(-50%);
        background:var(--input-bg);border-radius:20px;padding:8px 16px;box-shadow:0 2px 10px rgba(0,0,0,0.2);
        min-width:200px;z-index:500;">
        <div style="font-size:12px;margin-bottom:4px;color:var(--text-secondary)">Enviando arquivo...</div>
        <div style="background:rgba(0,0,0,0.1);border-radius:10px;height:4px;">
          <div class="upload-bar-fill" style="background:var(--accent);height:4px;border-radius:10px;width:0%;transition:width 0.2s;"></div>
        </div>
      </div>
    `);
    $('body').append($bar);
    return $bar;
  }

  function hideUploadProgress($bar) {
    $bar.fadeOut(300, () => $bar.remove());
  }

  // ── Download de arquivo ───────────────────────────────────
  function downloadFile(url, name) {
    const a     = document.createElement('a');
    a.href     = url;
    a.download = name || 'arquivo';
    a.target   = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  // ── Leitura de arquivo para IA ────────────────────────────
  function readFileForAI(url) {
    return $.get('api/files.php', { url }).then(data => {
      if (!data.success) throw new Error(data.error || 'Erro ao ler arquivo');

      if (data.type === 'image') {
        return { type: 'image', data: 'data:' + data.mime + ';base64,' + data.data };
      }

      return { type: 'text', content: data.content || '' };
    });
  }

  // ── Bind ──────────────────────────────────────────────────
  $(document).ready(() => {
    // Opções de upload
    $('#upload-image-btn').on('click', () => {
      $('#file-input-image').click();
      $('#upload-options').removeClass('open');
    });

    $('#upload-file-btn').on('click', () => {
      $('#file-input-file').click();
      $('#upload-options').removeClass('open');
    });

    // Inputs de arquivo ocultos
    $('#file-input-image').on('change', function() {
      if (this.files && this.files[0]) {
        uploadFile(this.files[0], 'image');
      }
    });

    $('#file-input-file').on('change', function() {
      if (this.files && this.files[0]) {
        uploadFile(this.files[0], 'file');
      }
    });

    // Drag and drop na área de mensagens
    const $chatArea = $('#messages-container');

    $chatArea.on('dragover', e => {
      e.preventDefault();
      $chatArea.css('outline', '2px dashed var(--accent)');
    });

    $chatArea.on('dragleave', () => {
      $chatArea.css('outline', '');
    });

    $chatArea.on('drop', e => {
      e.preventDefault();
      $chatArea.css('outline', '');
      const file = e.originalEvent.dataTransfer.files[0];
      if (file) uploadFile(file);
    });
  });

  return { uploadFile, downloadFile, readFileForAI, clearPendingFile };
})();
