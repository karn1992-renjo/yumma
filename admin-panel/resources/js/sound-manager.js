class SoundManager {
    constructor() {
        this.audioContext = null;
        this.init();
    }
    
    init() {
        // Create audio context on user interaction
        document.addEventListener('click', () => {
            if (!this.audioContext) {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
        }, { once: true });
    }
    
    playNewOrderSound() {
        if (!this.audioContext) return;
        
        try {
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);
            
            oscillator.frequency.value = 880;
            gainNode.gain.value = 0.3;
            
            oscillator.start();
            gainNode.gain.exponentialRampToValueAtTime(0.00001, this.audioContext.currentTime + 0.5);
            oscillator.stop(this.audioContext.currentTime + 0.5);
        } catch (e) {
            console.log('Could not play sound:', e);
        }
    }
}

export default new SoundManager();