import React from 'react';
import { Sun, Moon } from 'lucide-react';
import { colors } from '../styles/colors';

interface DarkModeToggleProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
}

const DarkModeToggle: React.FC<DarkModeToggleProps> = ({ checked, onChange }) => {
  const [isAnimating, setIsAnimating] = React.useState(false);

  const handleClick = () => {
    setIsAnimating(true);
    onChange(!checked);
    setTimeout(() => setIsAnimating(false), 500);
  };

  return (
    <button
      onClick={handleClick}
      style={{
        color: checked ? colors.pink[300] : colors.pink[500],
        fontSize: '22px',
        minWidth: '44px',
        minHeight: '44px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'none',
        border: 'none',
        cursor: 'pointer',
        padding: 0,
        outline: 'none',
        position: 'relative'
      }}
      aria-label={`Switch to ${checked ? 'light' : 'dark'} mode`}
      title={`Switch to ${checked ? 'light' : 'dark'} mode`}
    >
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          transition: 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)',
          transform: isAnimating ? 'rotate(360deg) scale(1.1)' : 'rotate(0deg) scale(1)'
        }}
      >
        {checked ? (
          <Moon
            size={22}
            strokeWidth={2}
            fill="currentColor"
            style={{
              display: 'block',
              transition: 'opacity 0.3s ease-in-out',
              opacity: 1
            }}
          />
        ) : (
          <Sun
            size={22}
            strokeWidth={2}
            fill="currentColor"
            style={{
              display: 'block',
              transition: 'opacity 0.3s ease-in-out',
              opacity: 1
            }}
          />
        )}
      </div>
    </button>
  );
};

export default DarkModeToggle;