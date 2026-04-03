/**
 * SETE — audio.js
 * Voice synthesis and speech recognition
 */

'use strict';

const AudioManager = {
  currentUtterance: null,
  recognition: null,
  isRecording: false,

  init() {
    // Preload voices
    if ('speechSynthesis' in window) {
      speechSynthesis.getVoices();
      speechSynthesis.addEventListener('voiceschanged', () => {});
    }
  },

  speak(text, voiceConfig = {}) {
    if (!('speechSynthesis' in window)) return null;
    speechSynthesis.cancel();

    const cleaned = text
      .replace(/```[\s\S]*?```/g, 'bloco de código')
      .replace(/`[^`]+`/g, 'código')
      .replace(/\*\*/g, '')
      .replace(/\*/g, '')
      .replace(/_/g, '')
      .replace(/#+\s/g, '')
      .replace(/https?:\/\/\S+/g, 'link')
      .trim();

    const utterance = new SpeechSynthesisUtterance(cleaned);
    utterance.lang  = 'pt-BR';
    utterance.rate  = voiceConfig.speed || 1.0;
    utterance.pitch = voiceConfig.pitch || 1.0;
    utterance.volume = 1.0;

    const voices = speechSynthesis.getVoices();
    const selected = this.selectVoice(voices, voiceConfig.type || 'feminina_adulta');
    if (selected) utterance.voice = selected;

    this.currentUtterance = utterance;
    speechSynthesis.speak(utterance);
    return utterance;
  },

  selectVoice(voices, type) {
    const ptVoices = voices.filter(v =>
      v.lang.startsWith('pt') || v.lang.startsWith('PT')
    );
    const allVoices = ptVoices.length > 0 ? ptVoices : voices;

    const female  = allVoices.filter(v => /female|fem|mulher|Luciana|Francisca|Maria|Victoria|Mônica/i.test(v.name));
    const male    = allVoices.filter(v => /male|masc|homem|Daniel|Ricardo|Jorge/i.test(v.name));
    const robotic = allVoices.filter(v => /espeakng|espeak|robotic/i.test(v.name));

    switch (type) {
      case 'feminina_adulta':
        return female[0] || ptVoices[0] || voices[0] || null;
      case 'masculina_adulto':
        return male[0] || ptVoices[1] || voices[1] || null;
      case 'crianca_menina':
        return female[0] || ptVoices[0] || null;
      case 'crianca_menino':
        return male[0] || ptVoices[0] || null;
      case 'idosa':
        return female[female.length - 1] || ptVoices[0] || null;
      case 'idoso':
        return male[male.length - 1] || ptVoices[0] || null;
      case 'robotica':
        return robotic[0] || voices[0] || null;
      default:
        return ptVoices[0] || voices[0] || null;
    }
  },

  stop() {
    if ('speechSynthesis' in window) {
      speechSynthesis.cancel();
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
