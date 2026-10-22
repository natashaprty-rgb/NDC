import { useEffect, useRef } from 'react';
import { colors } from '../styles/colors';

interface AsciiMorphTextProps {
  text: string;
}

const AsciiMorphText: React.FC<AsciiMorphTextProps> = ({ text }) => {
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const chars = '!<>-_\\/[]{}—=+*^?#________';
    const letters = text.split('');
    
    if (!containerRef.current) return;
    
    // Clear container
    containerRef.current.innerHTML = '';
    
    // Create spans for each letter
    letters.forEach((letter) => {
      const span = document.createElement('span');
      span.textContent = letter;
      span.style.opacity = '0';
      span.style.display = 'inline-block';
      span.style.minWidth = letter === ' ' ? '0.3em' : 'auto';
      containerRef.current?.appendChild(span);
    });
    
    const spans = containerRef.current.querySelectorAll('span');
    
    // Animate each letter
    spans.forEach((span, index) => {
      const letter = letters[index];
      let iterations = 0;
      
      setTimeout(() => {
        const interval = setInterval(() => {
          if (iterations < 10) {
            span.textContent = chars[Math.floor(Math.random() * chars.length)];
            span.style.opacity = '1';
            span.style.color = colors.pink[500]; // Darker dusty pink
          } else {
            span.textContent = letter;
            span.style.opacity = '1';
            span.style.color = colors.pink[600]; // Darker dusty pink
            clearInterval(interval);
          }
          iterations++;
        }, 50);
      }, index * 100); // Stagger the animation
    });
  }, [text]);

  return (
    <div 
      ref={containerRef}
      className="ascii-morph-text"
      style={{
        fontSize: 'clamp(1.5rem, 5vw, 3rem)',
        fontWeight: 'bold',
        fontFamily: 'monospace',
        letterSpacing: '0.05em',
        textAlign: 'left',
        margin: '0.5rem 0',
        textShadow: `0 0 15px ${colors.effects.glow}`,
      }}
    />
  );
};

export default AsciiMorphText;