import SwiftUI

/// "Nasi" the mascot. One static illustration, expressive via motion rather than
/// multiple drawn poses — idle bob, thinking bounce/wiggle, one-shot celebrate pop,
/// one-shot sad shake. Respects Reduce Motion (falls back to a plain fade-in).
enum MascotMood {
    case idle
    case thinking
    case celebrate
    case sad
}

struct MascotView: View {
    let mood: MascotMood
    var caption: String? = nil
    var size: CGFloat = 64

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var animate = false
    @State private var appeared = false

    var body: some View {
        VStack(spacing: 8) {
            Image("Mascot")
                .resizable()
                .scaledToFit()
                .frame(width: size, height: size)
                .accessibilityHidden(true)
                .offset(y: offsetY)
                .rotationEffect(.degrees(rotationDegrees))
                .scaleEffect(scale)
                .opacity(reduceMotion ? (appeared ? 1 : 0) : 1)
                .animation(reduceMotion ? .easeOut(duration: 0.25) : loopAnimation, value: animate)
                .animation(.easeOut(duration: 0.25), value: appeared)

            if let caption {
                Text(caption)
                    .font(.makanBody(14))
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
            }
        }
        .onAppear {
            appeared = true
            if !reduceMotion { animate = true }
        }
        .onChange(of: mood) { _, _ in
            animate = false
            if !reduceMotion {
                DispatchQueue.main.async { animate = true }
            }
        }
    }

    private var offsetY: CGFloat {
        guard !reduceMotion else { return 0 }
        switch mood {
        case .idle: return animate ? -6 : 0
        case .thinking: return animate ? -10 : 0
        case .celebrate, .sad: return 0
        }
    }

    private var rotationDegrees: Double {
        guard !reduceMotion else { return 0 }
        switch mood {
        case .idle: return 0
        case .thinking: return animate ? 4 : -4
        case .celebrate: return animate ? 0 : -6
        case .sad: return animate ? -6 : 6
        }
    }

    private var scale: CGFloat {
        guard !reduceMotion else { return 1 }
        switch mood {
        case .celebrate: return animate ? 1.0 : 0.85
        default: return 1
        }
    }

    private var loopAnimation: Animation {
        switch mood {
        case .idle:
            return .easeInOut(duration: 1.4).repeatForever(autoreverses: true)
        case .thinking:
            return .easeInOut(duration: 0.45).repeatForever(autoreverses: true)
        case .sad:
            return .easeInOut(duration: 0.09).repeatCount(6, autoreverses: true)
        case .celebrate:
            return .spring(response: 0.35, dampingFraction: 0.55)
        }
    }
}
