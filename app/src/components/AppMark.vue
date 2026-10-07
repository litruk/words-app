<template>
  <div class="app-mark" aria-hidden="true">
    <div class="app-mark__back" />
    <div class="app-mark__front">Aя</div>
  </div>
</template>

<style scoped>
.app-mark {
  position: relative;
  width: 88px;
  height: 80px;
  flex-shrink: 0;
}

.app-mark__back {
  position: absolute;
  left: 14px;
  top: 0;
  width: 70px;
  height: 70px;
  border-radius: 22px;
  background: var(--w-primary-soft);
  transform: rotate(9deg);
}

.app-mark__front {
  position: absolute;
  left: 0;
  top: 8px;
  width: 70px;
  height: 70px;
  border-radius: 22px;
  background: var(--w-primary);
  color: var(--w-on-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.02em;
}
</style>
