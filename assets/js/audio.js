/**
 * SETE - audio.js
 * Voice synthesis and speech recognition
 */

'use strict';

const AudioManager = {
  currentUtterance: null,
  recognition: null,
  isRecording: false,
  activeBtn: null,

  init() {
    if ('speechSynthesis' in window) {
      speechSynthesis.getVoices();
      speechSynthesis.addEventListener('voiceschanged', () => {});
    }
  },

  speak(text, voiceConfig = {}, btn = null) {
    if (!('speechSynthesis' in window)) return null;

    // Toggle pause/resume if same button clicked while speaking
    if (btn && this.activeBtn === btn && speechSynthesis.speaking) {
      if (speechSynthesis.paused) {
        speechSynthesis.resume();
        btn.textContent = '\u23F8 Pausar';
        btn.classList.add('playing');
      } else {
        speechSynthesis.pause();
        btn.textContent = '\u25B6 Continuar';
        btn.classList.remove('playing');
      }
      return this.currentUtterance;
    }

    // Cancel previous and reset old button
    speechSynthesis.cancel();
    if (this.activeBtn && this.activeBtn !== btn) {
      this.activeBtn.textContent = '\uD83D\uDD0A Ouvir';
      this.activeBtn.classList.remove('playing');
    }

    // Clean text: remove HTML tags, markdown, images, links
    const cleaned = text
      .replace(/<[^>]+>/g, ' ')
      .replace(/```[\s\S]*?```/g, 'bloco de codigo')
      .replace(/`[^`]+`/g, 'codigo')
      .replace(/\*\*/g, '')
      .replace(/\*/g, '')
      .replace(/_/g, '')
      .replace(/#+\s/g, '')
      .replace(/https?:\/\/\S+/g, 'link')
      .replace(/!\[[^\]]*\]\([^)]*\)/g, '')
      .replace(/\[[^\]]*\]\([^)]*\)/g, '')
      .replace(/\s{2,}/g, ' ')
      .trim();

    if (!cleaned) return null;

    const utterance = new SpeechSynthesisUtterance(cleaned);
    utterance.lang   = 'pt-BR';
    utterance.rate   = voiceConfig.speed || 0.95;
    utterance.pitch  = voiceConfig.pitch || 1.05;
    utterance.volume = 1.0;

    const voices   = speechSynthesis.getVoices();
    const selected = this.selectVoice(voices, voiceConfig.type || 'feminina_adulta');
    if (selected) utterance.voice = selected;

    if (btn) {
      btn.textContent = '\u23F8 Pausar';
      btn.classList.add('playing');
      this.activeBtn = btn;

      utterance.onend = () => {
        btn.textContent = '\uD83D\uDD0A Ouvir';
        btn.classList.remove('playing');
        if (this.activeBtn === btn) this.activeBtn = null;
      };
      utterance.onerror = () => {
        btn.textContent = '\uD83D\uDD0A Ouvir';
        btn.classList.remove('playing');
        if (this.activeBtn === btn) this.activeBtn = null;
      };
    }

    this.currentUtterance = utterance;
    speechSynthesis.speak(utterance);
    return utterance;
  },

  selectVoice(voices, type) {
    const ptVoices = voices.filter(v =>
      v.lang.startsWith('pt') || v.lang.startsWith('PT')
    );
    const allVoices = ptVoices.length > 0 ? ptVoices : voices;

    // Prefer Google/Microsoft/Apple voices for natural sound
    const naturalFemale = allVoices.filter(v =>
      /Google|Microsoft|Apple/i.test(v.name) &&
      /female|fem|mulher|Luciana|Francisca|Maria|Victoria|Monica|Camila|Fernanda/i.test(v.name)
    );
    const naturalMale = allVoices.filter(v =>
      /Google|Microsoft|Apple/i.test(v.name) &&
      /male|masc|homem|Daniel|Ricardo|Jorge|Carlos/i.test(v.name)
    );

    const female  = allVoices.filter(v => /female|fem|mulher|Luciana|Francisca|Maria|Victoria|Monica|Camila|Fernanda/i.test(v.name));
    const male    = allVoices.filter(v => /male|masc|homem|Daniel|Ricardo|Jorge|Carlos/i.test(v.name));
    const robotic = allVoices.filter(v => /espeakng|espeak|robotic/i.test(v.name));

    switch (type) {
      case 'feminina_adulta':
        return naturalFemale[0] || female[0] || ptVoices[0] || voices[0] || null;
      case 'masculina_adulto':
        return naturalMale[0] || male[0] || ptVoices[1] || voices[1] || null;
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
    if (this.activeBtn) {
      this.activeBtn.textContent = '\uD83D\uDD0A Ouvir';
      this.activeBtn.classList.remove('playing');
      this.activeBtn = null;
    }
  },

  isSpeaking() {
    return 'speechSynthesis' in window && speechSynthesis.speaking;
  },

  startRecording(onResult, onError) {
    const SpeechRecognition =
      window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
      if (onError) onError('Reconhecimento de voz nao suportado neste navegador.');
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
