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
  _ttsAbortId: 0,

  init() {
    if ('speechSynthesis' in window) {
      speechSynthesis.getVoices();
      speechSynthesis.addEventListener('voiceschanged', () => {});
    }
  },

  speak(text, voiceConfig = {}, btn = null) {
    // If same button is active, toggle pause/resume instead of restarting
    if (btn && this.activeBtn === btn) {
      if (this.activeElevenLabsAudio) {
        if (this.activeElevenLabsAudio.paused) {
          this.activeElevenLabsAudio.play();
          btn.textContent = '\u23F8 Pausar';
          btn.classList.add('playing');
        } else {
          this.activeElevenLabsAudio.pause();
          btn.textContent = '\u25B6 Continuar';
          btn.classList.remove('playing');
        }
        return null;
      }
      if (this.activeGoogleTTSAudio) {
        if (this.activeGoogleTTSAudio.paused) {
          this.activeGoogleTTSAudio.play();
          btn.textContent = '\u23F8 Pausar';
          btn.classList.add('playing');
        } else {
          this.activeGoogleTTSAudio.pause();
          btn.textContent = '\u25B6 Continuar';
          btn.classList.remove('playing');
        }
        return null;
      }
      if ('speechSynthesis' in window && speechSynthesis.speaking) {
        if (speechSynthesis.paused) {
          speechSynthesis.resume();
          btn.textContent = '\u23F8 Pausar';
          btn.classList.add('playing');
        } else {
          speechSynthesis.pause();
          btn.textContent = '\u25B6 Continuar';
          btn.classList.remove('playing');
        }
        return null;
      }
      // Still loading (fetch in progress) — prevent re-trigger
      return null;
    }

    // If ElevenLabs Voice ID is configured, use ElevenLabs TTS
    if (voiceConfig.elevenLabsId) {
      return this._speakElevenLabs(text, voiceConfig.elevenLabsId, btn);
    }

    // Clean text: remove HTML tags, markdown, images, links, emojis/symbols
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
      .replace(/\p{Extended_Pictographic}/gu, '')
      .replace(/\s{2,}/g, ' ')
      .trim();

    if (!cleaned) return null;

    // Try Google TTS first, fallback to Web Speech API
    this._speakGoogleTTS(cleaned, voiceConfig, btn);
    return null;
  },

  _speakGoogleTTS(cleaned, voiceConfig, btn = null) {
    // Toggle pause/resume if same button clicked while audio is playing
    if (btn && this.activeBtn === btn && this.activeGoogleTTSAudio) {
      const audio = this.activeGoogleTTSAudio;
      if (audio.paused) {
        audio.play();
        btn.textContent = '\u23F8 Pausar';
        btn.classList.add('playing');
      } else {
        audio.pause();
        btn.textContent = '\u25B6 Continuar';
        btn.classList.remove('playing');
      }
      return;
    }

    // Stop any current speech
    if ('speechSynthesis' in window) speechSynthesis.cancel();
    if (this.activeGoogleTTSAudio) {
      this.activeGoogleTTSAudio.pause();
      this.activeGoogleTTSAudio = null;
    }
    if (this.activeBtn && this.activeBtn !== btn) {
      this.activeBtn.textContent = '\uD83D\uDD0A Ouvir';
      this.activeBtn.classList.remove('playing');
    }

    if (btn) {
      btn.textContent = '\u23F8 Pausar';
      btn.classList.add('playing');
      this.activeBtn = btn;
    }

    const abortId = ++this._ttsAbortId;

    fetch('api/google_tts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ text: cleaned, voice_type: voiceConfig.type || 'feminina_adulta' }),
    })
      .then(res => {
        if (!res.ok || !res.headers.get('content-type')?.includes('audio/wav')) {
          throw new Error(`Google TTS indisponível (HTTP ${res.status})`);
        }
        return res.blob();
      })
      .then(blob => {
        if (this._ttsAbortId !== abortId) { return; }
        const url = URL.createObjectURL(blob);
        const audio = new Audio(url);
        this.activeGoogleTTSAudio = audio;

        if (btn) {
          btn.textContent = '\u23F8 Pausar';
          btn.classList.add('playing');

          audio.onended = () => {
            btn.textContent = '\uD83D\uDD0A Ouvir';
            btn.classList.remove('playing');
            if (this.activeBtn === btn) this.activeBtn = null;
            if (this.activeGoogleTTSAudio === audio) this.activeGoogleTTSAudio = null;
            URL.revokeObjectURL(url);
          };
          audio.onerror = () => {
            btn.textContent = '\uD83D\uDD0A Ouvir';
            btn.classList.remove('playing');
            if (this.activeBtn === btn) this.activeBtn = null;
            if (this.activeGoogleTTSAudio === audio) this.activeGoogleTTSAudio = null;
            URL.revokeObjectURL(url);
          };
        }

        audio.play();
      })
      .catch(() => {
        if (this._ttsAbortId !== abortId) return;
        // Fallback to Web Speech API
        this._speakWebSpeech(cleaned, voiceConfig, btn);
      });
  },

  _speakWebSpeech(cleaned, voiceConfig, btn = null) {
    if (!('speechSynthesis' in window)) {
      if (btn) {
        btn.textContent = '\uD83D\uDD0A Ouvir';
        btn.classList.remove('playing');
        if (this.activeBtn === btn) this.activeBtn = null;
      }
      return null;
    }

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

  _speakElevenLabs(text, voiceId, btn = null) {
    // Stop any current speech
    if ('speechSynthesis' in window) speechSynthesis.cancel();
    if (this.activeElevenLabsAudio) {
      this.activeElevenLabsAudio.pause();
      this.activeElevenLabsAudio = null;
    }
    if (this.activeBtn && this.activeBtn !== btn) {
      this.activeBtn.textContent = '\uD83D\uDD0A Ouvir';
      this.activeBtn.classList.remove('playing');
    }

    // Clean text
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

    if (btn) {
      btn.textContent = '\u23F8 Pausar';
      btn.classList.add('playing');
      this.activeBtn = btn;
    }

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const headers = { 'Content-Type': 'application/json' };
    if (csrfMeta) headers['X-CSRF-Token'] = csrfMeta.content;

    const abortId = ++this._ttsAbortId;

    fetch('api/elevenlabs_tts.php', {
      method: 'POST',
      headers,
      body: JSON.stringify({ text: cleaned, voice_id: voiceId }),
    })
      .then(res => {
        if (!res.ok) {
          return res.json().then(err => { throw new Error(err.error || 'Erro ElevenLabs'); });
        }
        return res.blob();
      })
      .then(blob => {
        if (this._ttsAbortId !== abortId) { return; }
        const url = URL.createObjectURL(blob);
        const audio = new Audio(url);
        this.activeElevenLabsAudio = audio;

        if (btn) {
          btn.textContent = '\u23F8 Pausar';
          btn.classList.add('playing');

          audio.onended = () => {
            btn.textContent = '\uD83D\uDD0A Ouvir';
            btn.classList.remove('playing');
            if (this.activeBtn === btn) this.activeBtn = null;
            if (this.activeElevenLabsAudio === audio) this.activeElevenLabsAudio = null;
            URL.revokeObjectURL(url);
          };
          audio.onerror = () => {
            btn.textContent = '\uD83D\uDD0A Ouvir';
            btn.classList.remove('playing');
            if (this.activeBtn === btn) this.activeBtn = null;
            if (this.activeElevenLabsAudio === audio) this.activeElevenLabsAudio = null;
            URL.revokeObjectURL(url);
          };
        }

        audio.play();
      })
      .catch(err => {
        if (this._ttsAbortId !== abortId) return;
        if (btn) {
          btn.textContent = '\uD83D\uDD0A Ouvir';
          btn.classList.remove('playing');
          if (this.activeBtn === btn) this.activeBtn = null;
        }
        console.error('ElevenLabs TTS error:', err.message);
      });

    return null;
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
    this._ttsAbortId++;
    if ('speechSynthesis' in window) {
      speechSynthesis.cancel();
    }
    if (this.activeGoogleTTSAudio) {
      this.activeGoogleTTSAudio.pause();
      this.activeGoogleTTSAudio.currentTime = 0;
      this.activeGoogleTTSAudio = null;
    }
    if (this.activeElevenLabsAudio) {
      this.activeElevenLabsAudio.pause();
      this.activeElevenLabsAudio.currentTime = 0;
      this.activeElevenLabsAudio = null;
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
