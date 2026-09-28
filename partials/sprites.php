<!-- definições compartilhadas: gradientes das azeitonas e pimentas (dos dois lados) e selos -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <?php foreach (SIDES as $sd) echo $sd['defs']; ?>
    <clipPath id="selo-clip" clipPathUnits="objectBoundingBox"><circle cx=".5" cy=".5" r=".5"/></clipPath>
    <symbol id="selo-br" viewBox="-10 -10 20 20">
      <clipPath id="selo-br-clip"><circle r="10"/></clipPath>
      <g clip-path="url(#selo-br-clip)">
        <rect x="-10" y="-10" width="20" height="20" fill="#009c3b"/>
        <path d="M0 -7.6 L9.6 0 L0 7.6 L-9.6 0 Z" fill="#ffdf00"/>
        <circle r="4.3" fill="#002776"/>
        <path d="M-4.2 -.6 Q0 -2.4 4.2 .8" stroke="#fff" stroke-width="1" fill="none"/>
      </g>
    </symbol>
  </defs>
</svg>
