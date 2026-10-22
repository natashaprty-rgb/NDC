import React from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { useThemeColors } from '../hooks/useThemeColors';

interface BackButtonProps {
  to?: string;
  scrollToId?: string;
  label?: string;
  ariaLabel?: string;
}

const BackButton: React.FC<BackButtonProps> = ({ 
  to = '/', 
  scrollToId = 'projects',
  label = 'Back to Projects',
  ariaLabel = 'Navigate back to projects section'
}) => {
  const themeColors = useThemeColors();
  const navigate = useNavigate();

  const handleClick = () => {
    navigate(to);
    if (scrollToId) {
      setTimeout(() => {
        const element = document.getElementById(scrollToId);
        if (element) {
          element.scrollIntoView({ behavior: 'smooth' });
        }
      }, 100);
    }
  };

  return (
    <button 
      onClick={handleClick}
      aria-label={ariaLabel}
      className="inline-flex items-center gap-2 hover:opacity-80 mb-8 transition-colors bg-transparent border-none cursor-pointer" 
      style={{ color: themeColors.text.pink }}
    >
      <ArrowLeft className="h-5 w-5" aria-hidden="true" />
      <span>{label}</span>
    </button>
  );
};

export default BackButton;