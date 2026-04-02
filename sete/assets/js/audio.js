/**
 * WhatsappJUJU — Áudio: TTS e STT (audio.js)
 */

const Audio = (() => {
  let synth         = window.speechSynthesis;
  let recognition   = null;
  let isRecording   = false;
  let currentUtter  = null;
  let voicesLoaded  = false;

  // ── Inicializa vozes ──────────────────────────────────────
  function init() {
    if (synth) {
      synth.onvoiceschanged = () => { voicesLoaded = true; };
      synth.getVoices(); // Trigger load
    }

    // SpeechRecognition (STT)
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
      recognition = new SpeechRecognition();
      recognition.lang          = 'pt-BR';
      recognition.interimResults= false;
      recognition.maxAlternatives = 1;

      recognition.onresult = e => {
        const transcript = e.results[0][0].transcript;
        const $input     = $('#message-input');
        $input.val(($input.val() + ' ' + transcript).trim());
        $input.trigger('input');
      };

      recognition.onerror = e => {
        App.showToast('Erro no reconhecimento de voz: ' + e.error, 'error');
        stopRecording();
      };

      recognition.onend = () => {
        isRecording = false;
        $('#mic-btn').removeClass('recording');
      };
    }
  }

  // ── TTS: falar texto ──────────────────────────────────────
  function speakText(text, voiceConfig) {
    if (!synth) {
      App.showToast('Seu navegador não suporta síntese de voz.', 'info');
      return;
    }

    // Para qualquer fala em andamento
    synth.cancel();

    // Remove markdown básico para fala mais limpa
    const cleanText = text
      .replace(/```[\s\S]*?```/g, 'código')
      .replace(/`([^`]+)`/g, '$1')
      .replace(/\*\*(.+?)\*\*/g, '$1')
      .replace(/\*(.+?)\*/g, '$1')
      .replace(/_(.+?)_/g, '$1')
      .replace(/#{1,6}\s/g, '')
      .replace(/\[([^\]]+)\]\([^\)]+\)/g, '$1')
      .replace(/\n+/g, '. ');

    const utterance  = new SpeechSynthesisUtterance(cleanText);
    utterance.lang   = 'pt-BR';
    utterance.rate   = parseFloat(voiceConfig.voice_speed) || 1.0;
    utterance.pitch  = parseFloat(voiceConfig.voice_pitch) || 1.0;

    // Seleciona voz
    const voices      = synth.getVoices();
    utterance.voice   = selectVoice(voices, voiceConfig.voice_type);

    currentUtter = utterance;
    synth.speak(utterance);
  }

  // ── Selecionar voz por tipo ───────────────────────────────
  function selectVoice(voices, type) {
    if (!voices || voices.length === 0) return null;

    const ptVoices = voices.filter(v => v.lang.startsWith('pt'));
    const pool     = ptVoices.length > 0 ? ptVoices : voices;

    // Mapeamento de tipos para características de voz
    const isFemale = type && (type.includes('feminina') || type.includes('menina') || type.includes('sussurro'));
    const isMale   = type && (type.includes('masculin') || type.includes('menino'));

    if (isFemale) {
      const female = pool.find(v => v.name.toLowerCase().includes('female') || v.name.toLowerCase().includes('femi') || v.name.includes('Google português do Brasil'));
      if (female) return female;
    }

    if (isMale) {
      const male = pool.find(v => v.name.toLowerCase().includes('male') || v.name.toLowerCase().includes('masc'));
      if (male) return male;
    }

    return pool[0] || null;
  }

  // ── Para fala ─────────────────────────────────────────────
  function stopSpeaking() {
    if (synth) synth.cancel();
    currentUtter = null;
  }

  // ── STT: iniciar gravação ─────────────────────────────────
  function startRecording(e) {
    e.preventDefault();
    if (!recognition) {
      App.showToast('Reconhecimento de voz não suportado neste navegador.', 'info');
      return;
    }
    if (isRecording) return;

    isRecording = true;
    $('#mic-btn').addClass('recording');

    try {
      recognition.start();
    } catch (err) {
      isRecording = false;
      $('#mic-btn').removeClass('recording');
    }
  }

  // ── STT: parar gravação ───────────────────────────────────
  function stopRecording(e) {
    if (e) e.preventDefault();
    if (!recognition || !isRecording) return;
    recognition.stop();
    isRecording = false;
    $('#mic-btn').removeClass('recording');
  }

  // ── ElevenLabs TTS ────────────────────────────────────────
  function speakElevenLabs(text, voiceId, apiKey) {
    return $.ajax({
      url:  'api/tts.php',
      type: 'POST',
      data: { text, voice_id: voiceId, api_key: apiKey },
    }).then(data => {
      if (!data.success) throw new Error(data.error || 'Erro ElevenLabs');
      const audioData = 'data:' + data.mime + ';base64,' + data.audio;
      const audio     = new window.Audio(audioData);
      audio.play();
      return audio;
    });
  }

  // ── Init ──────────────────────────────────────────────────
  $(document).ready(init);

  return {
    speakText,
    stopSpeaking,
    startRecording,
    stopRecording,
    speakElevenLabs,
    get isSpeaking() { return synth ? synth.speaking : false; },
  };
})();
