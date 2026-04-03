/**
 * SETE — audio.js
 * Voice synthesis and speech recognition
 */

'use strict';

const AudioManager = {
  currentUtterance: null,
  currentBtn: null,
  recognition: null,
  isRecording: false,
  _voicesLoaded: false,
  _voices: [],

  init() {
    // Preload voices (Chrome requires waiting for voiceschanged)
    if ('speechSynthesis' in window) {
      const loadVoices = () => {
        this._voices = speechSynthesis.getVoices();
        this._voicesLoaded = this._voices.length > 0;
      };
      loadVoices();
      speechSynthesis.addEventListener('voiceschanged', loadVoices);
    }
  },

  // Strip HTML tags, markdown, emoji and image URLs from text for TTS
  cleanTextForSpeech(text) {
    if (!text) return '';
    return text
      // Remove HTML tags
      .replace(/<[^>]+>/g, ' ')
      // Remove image URLs and markdown images
      .replace(/!\[.*?\]\(.*?\)/g, '')
      .replace(/https?:\/\/\S+\.(jpg|jpeg|png|gif|webp|svg)[^\s]*/gi, '')
      // Remove code blocks (replace with description)
      .replace(/```[\s\S]*?```/g, ' bloco de código ')
      .replace(/`[^`]+`/g, ' código ')
      // Remove markdown formatting
      .replace(/\*\*\*(.+?)\*\*\*/g, '$1')
      .replace(/\*\*(.+?)\*\*/g, '$1')
      .replace(/\*(.+?)\*/g, '$1')
      .replace(/__(.+?)__/g, '$1')
      .replace(/_(.+?)_/g, '$1')
      .replace(/#+\s/g, '')
      // Remove links (keep text)
      .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
      .replace(/https?:\/\/\S+/g, ' link ')
      // Remove emoji (unicode emoji ranges)
      .replace(/[\u{1F600}-\u{1F64F}]/gu, '')
      .replace(/[\u{1F300}-\u{1F5FF}]/gu, '')
      .replace(/[\u{1F680}-\u{1F6FF}]/gu, '')
      .replace(/[\u{1F700}-\u{1F77F}]/gu, '')
      .replace(/[\u{1F780}-\u{1F7FF}]/gu, '')
      .replace(/[\u{1F800}-\u{1F8FF}]/gu, '')
      .replace(/[\u{1F900}-\u{1F9FF}]/gu, '')
      .replace(/[\u{1FA00}-\u{1FA6F}]/gu, '')
      .replace(/[\u{2600}-\u{26FF}]/gu, '')
      .replace(/[\u{2700}-\u{27BF}]/gu, '')
      .replace(/[\u{FE00}-\u{FE0F}]/gu, '')
      // Clean up extra whitespace
      .replace(/\s+/g, ' ')
      .trim();
  },

  // Voice pitch/rate presets per type
  getVoicePreset(type) {
    const presets = {
      'feminina_adulta':   { pitch: 1.1, rate: 0.95 },
      'feminina_jovem':    { pitch: 1.2, rate: 1.05 },
      'masculina_adulto':  { pitch: 0.9, rate: 0.95 },
      'masculino_jovem':   { pitch: 1.0, rate: 1.05 },
      'crianca_menina':    { pitch: 1.3, rate: 1.1 },
      'crianca_menino':    { pitch: 1.1, rate: 1.1 },
      'idosa':             { pitch: 0.9, rate: 0.85 },
      'idoso':             { pitch: 0.8, rate: 0.85 },
      'robotica':          { pitch: 0.7, rate: 0.9 },
    };
    return presets[type] || { pitch: 1.0, rate: 1.0 };
  },

  speak(text, voiceConfig = {}, onEnd = null) {
    if (!('speechSynthesis' in window)) return null;
    speechSynthesis.cancel();

    const cleaned = this.cleanTextForSpeech(text);
    if (!cleaned) return null;

    const type    = voiceConfig.type || 'feminina_adulta';
    const preset  = this.getVoicePreset(type);

    const utterance  = new SpeechSynthesisUtterance(cleaned);
    utterance.lang   = 'pt-BR';
    utterance.rate   = voiceConfig.speed || preset.rate;
    utterance.pitch  = voiceConfig.pitch || preset.pitch;
    utterance.volume = 1.0;

    const voices   = this._voicesLoaded ? this._voices : speechSynthesis.getVoices();
    const selected = this.selectVoice(voices, type);
    if (selected) utterance.voice = selected;

    utterance.onend = () => {
      this.currentUtterance = null;
      if (onEnd) onEnd();
    };
    utterance.onerror = () => {
      this.currentUtterance = null;
      if (onEnd) onEnd();
    };

    this.currentUtterance = utterance;
    speechSynthesis.speak(utterance);
    return utterance;
  },

  // Toggle pause/resume for current utterance
  togglePause() {
    if (!('speechSynthesis' in window)) return;
    if (speechSynthesis.paused) {
      speechSynthesis.resume();
    } else if (speechSynthesis.speaking) {
      speechSynthesis.pause();
    }
  },

  isPaused() {
    return 'speechSynthesis' in window && speechSynthesis.paused;
  },

  selectVoice(voices, type) {
    const ptVoices = voices.filter(v =>
      v.lang.startsWith('pt') || v.lang.startsWith('PT')
    );
    const allVoices = ptVoices.length > 0 ? ptVoices : voices;

    // Prefer non-robotic/non-espeak voices
    const humanVoices = allVoices.filter(v => !/espeakng|espeak/i.test(v.name));
    const pool = humanVoices.length > 0 ? humanVoices : allVoices;

    const female = pool.filter(v =>
      /female|fem|mulher|Luciana|Francisca|Maria|Victoria|Mônica|Raquel|Helena/i.test(v.name)
    );
    const male = pool.filter(v =>
      /male|masc|homem|Daniel|Ricardo|Antonio|Jorge|Bruno/i.test(v.name)
    );
    const robotic = allVoices.filter(v => /espeakng|espeak|robotic/i.test(v.name));

    switch (type) {
      case 'feminina_adulta':
      case 'feminina_jovem':
        return female[0] || pool[0] || voices[0] || null;
      case 'masculina_adulto':
      case 'masculino_jovem':
        return male[0] || pool[1] || voices[1] || null;
      case 'crianca_menina':
        return female[0] || pool[0] || null;
      case 'crianca_menino':
        return male[0] || pool[0] || null;
      case 'idosa':
        return female[female.length - 1] || pool[0] || null;
      case 'idoso':
        return male[male.length - 1] || pool[0] || null;
      case 'robotica':
        return robotic[0] || voices[0] || null;
      default:
        return pool[0] || voices[0] || null;
    }
  },

  stop() {
    if ('speechSynthesis' in window) {
      speechSynthesis.cancel();
      this.currentUtterance = null;
    }
  },

  isSpeaking() {
    return 'speechSynthesis' in window && speechSynthesis.speaking;
  },

  startRecording(onResult, onError) {
    const SpeechRecognition =
      window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
      if (onError) onError('Reconhecimento de voz não suportado neste navegador.');
      return false;
    }

    if (this.isRecording) {
      this.stopRecording();
      return false;
    }

    try {
      this.recognition = new SpeechRecognition();
      this.recognition.lang = 'pt-BR';
      this.recognition.continuous = false;
      this.recognition.interimResults = true;
      this.recognition.maxAlternatives = 1;

      this.recognition.onresult = (event) => {
        const transcript = Array.from(event.results)
          .map(r => r[0].transcript)
          .join('');
        const isFinal = event.results[event.results.length - 1].isFinal;
        if (onResult) onResult(transcript, isFinal);
      };

      this.recognition.onerror = (event) => {
        this.isRecording = false;
        if (onError) onError('Erro no reconhecimento: ' + event.error);
      };

      this.recognition.onend = () => {
        this.isRecording = false;
      };

      this.recognition.start();
      this.isRecording = true;
      return true;
    } catch (e) {
      if (onError) onError(e.message);
      return false;
    }
  },

  stopRecording() {
    if (this.recognition) {
      this.recognition.stop();
      this.recognition = null;
    }
    this.isRecording = false;
  },
};

window.AudioManager = AudioManager;
