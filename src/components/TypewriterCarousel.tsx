import { useState, useEffect } from 'react';

interface TypewriterCarouselProps {
  roles: string[];
  typingSpeed?: number;
  deletingSpeed?: number;
  pauseDuration?: number;
  className?: string;
}

const TypewriterCarousel = ({
  roles,
  typingSpeed = 100,
  deletingSpeed = 50,
  pauseDuration = 2000,
  className = ''
}: TypewriterCarouselProps) => {
  const [currentRoleIndex, setCurrentRoleIndex] = useState(0);
  const [displayText, setDisplayText] = useState('');
  const [isDeleting, setIsDeleting] = useState(false);
  const [isPaused, setIsPaused] = useState(false);

  useEffect(() => {
    const currentRole = roles[currentRoleIndex];
    
    if (isPaused) {
      const pauseTimeout = setTimeout(() => {
        setIsPaused(false);
        setIsDeleting(true);
      }, pauseDuration);
      return () => clearTimeout(pauseTimeout);
    }

    if (!isDeleting) {
      // Typing
      if (displayText.length < currentRole.length) {
        const typingTimeout = setTimeout(() => {
          setDisplayText(currentRole.slice(0, displayText.length + 1));
        }, typingSpeed);
        return () => clearTimeout(typingTimeout);
      } else {
        // Finished typing, pause before deleting
        setIsPaused(true);
      }
    } else {
      // Deleting
      if (displayText.length > 0) {
        const deletingTimeout = setTimeout(() => {
          setDisplayText(displayText.slice(0, -1));
        }, deletingSpeed);
        return () => clearTimeout(deletingTimeout);
      } else {
        // Finished deleting, move to next role
        setIsDeleting(false);
        setCurrentRoleIndex((prevIndex) => (prevIndex + 1) % roles.length);
      }
    }
  }, [displayText, isDeleting, isPaused, currentRoleIndex, roles, typingSpeed, deletingSpeed, pauseDuration]);

  return (
    <span className={`typewriter ${className}`}>
      {displayText}
      <span className="cursor">|</span>
    </span>
  );
};

export default TypewriterCarousel;