/**
 * WhatsappJUJU — Áudio: TTS e STT (audio.js)
 */

const Audio = (() => {
  let synth         = window.speechSynthesis;
  let recognition   = null;
  let isRecording   = false;
  let currentUtter  = null;
  let cachedVoices  = [];
  let voicesLoaded  = false;

  // ── Carrega e mantém cache de vozes ───────────────────────
  function loadVoices() {
    const voices = synth ? synth.getVoices() : [];
    if (voices.length > 0) {
      cachedVoices = voices;
      voicesLoaded = true;
    }
  }

  // ── Inicializa vozes ──────────────────────────────────────
  function init() {
    if (synth) {
      synth.onvoiceschanged = loadVoices;
      loadVoices(); // tenta carregar imediatamente
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

    const utterance = new SpeechSynthesisUtterance(cleanText);
    utterance.lang  = 'pt-BR';

    const voiceType = voiceConfig.voice_type || 'feminina_adulta';

    // Aplica pitch e rate conforme o tipo de voz
    const voiceParams = getVoiceParams(voiceType);
    utterance.rate   = (voiceConfig.voice_speed !== null && voiceConfig.voice_speed !== undefined)
      ? parseFloat(voiceConfig.voice_speed)
      : voiceParams.rate;
    utterance.pitch  = (voiceConfig.voice_pitch !== null && voiceConfig.voice_pitch !== undefined)
      ? parseFloat(voiceConfig.voice_pitch)
      : voiceParams.pitch;
    if (voiceParams.volume !== undefined) {
      utterance.volume = voiceParams.volume;
    }

    // Seleciona voz
    utterance.voice = selectVoice(voiceType);

    currentUtter = utterance;
    synth.speak(utterance);
  }

  // ── Parâmetros de pitch/rate por tipo ─────────────────────
  function getVoiceParams(voiceType) {
    const map = {
      feminina_adulta:  { pitch: 1.0, rate: 1.0 },
      feminina_jovem:   { pitch: 1.2, rate: 1.1 },
      feminina_idosa:   { pitch: 0.8, rate: 0.85 },
      masculina_adulto: { pitch: 1.0, rate: 1.0 },
      masculino_jovem:  { pitch: 1.3, rate: 1.1 },
      masculino_idoso:  { pitch: 0.7, rate: 0.8 },
      crianca_menina:   { pitch: 1.5, rate: 1.2 },
      crianca_menino:   { pitch: 1.5, rate: 1.2 },
      robotica:         { pitch: 0.3, rate: 0.9 },
      dramatica:        { pitch: 0.6, rate: 0.7 },
      sussurro:         { pitch: 1.0, rate: 0.6, volume: 0.3 },
    };
    return map[voiceType] || { pitch: 1.0, rate: 1.0 };
  }

  // ── Selecionar voz por tipo ───────────────────────────────
  function selectVoice(voiceType) {
    // Usa cache se disponível, senão tenta obter ao vivo
    const voices = cachedVoices.length > 0 ? cachedVoices : (synth ? synth.getVoices() : []);
    if (!voices || voices.length === 0) return null;

    // Filtra por idioma: pt-BR primeiro, depois pt-PT, depois qualquer pt, fallback inglês
    let ptBR    = voices.filter(v => v.lang === 'pt-BR');
    let ptPT    = voices.filter(v => v.lang === 'pt-PT');
    let ptAny   = voices.filter(v => v.lang.startsWith('pt'));
    let enVoices= voices.filter(v => v.lang.startsWith('en'));

    const pool = ptBR.length > 0 ? ptBR
               : ptPT.length > 0 ? ptPT
               : ptAny.length > 0 ? ptAny
               : enVoices.length > 0 ? enVoices
               : voices;

    const isFemale = voiceType.includes('feminina') || voiceType.includes('menina');
    const isMale   = voiceType.includes('masculin') || voiceType.includes('menino');

    // Nomes femininos conhecidos
    const femaleKeywords = ['female', 'feminino', 'maria', 'francisca', 'luciana', 'vitoria',
                            'ana', 'isabela', 'camila', 'leila', 'woman', 'girl', 'femi'];
    // Nomes masculinos conhecidos
    const maleKeywords   = ['male', 'masculino', 'daniel', 'ricardo', 'carlos', 'jorge',
                            'pedro', 'man', 'boy', 'masc'];

    let selected = null;

    if (isFemale) {
      selected = pool.find(v => {
        const n = v.name.toLowerCase();
        return femaleKeywords.some(k => n.includes(k));
      });
    } else if (isMale) {
      selected = pool.find(v => {
        const n = v.name.toLowerCase();
        return maleKeywords.some(k => n.includes(k));
      });
    }

    // Fallback: usa a primeira voz do pool filtrado por idioma
    if (!selected) selected = pool[0] || voices[0] || null;

    return selected;
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
